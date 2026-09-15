<?php

namespace App\Services\PM;

use App\Models\Machine;
use App\Models\PmSchedule;
use App\Services\Auth\ActivityLogService;
use Illuminate\Support\Facades\DB;

/**
 * Otoritas transisi lifecycle Schedule (TASK-006 Slice B / ADR-010).
 *
 * Kelas ini adalah satu-satunya jalur mutasi lifecycle Schedule untuk pause,
 * resume, dan end. Setiap transisi:
 * - wajib membawa reason (alasan) yang tidak kosong;
 * - menulis audit melalui ActivityLogService pada transaksi database yang
 *   SAMA dengan mutasi lifecycle, sehingga kegagalan audit akan menggagalkan
 *   (rollback) transisi itu sendiri;
 * - memvalidasi provenance assignment: baris schedule yang sudah di-lock
 *   harus tetap menunjuk Machine yang sama dengan Machine yang dikunci,
 *   sehingga caller yang memegang snapshot basi (stale) tidak dapat
 *   menjalankan transisi di bawah lock Machine yang salah.
 *
 * Proyeksi kompatibilitas is_active selalu ditulis pada operasi persist yang
 * sama dengan lifecycle_status, state terminal 'ended' hanya dapat dihasilkan
 * di sini, dan keputusan transisi dibaca dari lifecycle_status, bukan dari
 * boolean legacy.
 *
 * Urutan lock mengikuti kontrak TASK-006: Machine -> PmSchedule. Occurrence
 * (PmScheduleDate) dan execution canonical tidak pernah ditulis di sini,
 * sehingga pekerjaan yang sudah dimulai dan histori tetap utuh.
 *
 * Actor audit memakai mekanisme autentikasi/audit yang sudah ada
 * (PrimeAuthService di ActivityLogService); tidak ada tabel audit paralel
 * maupun kolom schema baru. Tanpa model event, mutator, trigger, atau efek
 * samping tersembunyi: LifecyclePolicy tetap murni dan hanya menjadi sumber
 * matriks transisi.
 */
class ScheduleLifecycleService
{
    public function __construct(
        protected LifecyclePolicy $lifecyclePolicy,
        protected ActivityLogService $activityLog,
    ) {}

    /**
     * Menerapkan satu transisi lifecycle Schedule secara transaksional.
     *
     * @param  PmSchedule  $schedule  Snapshot pemanggil; state authoritative diambil ulang di bawah lock.
     * @param  string  $target  Status tujuan: 'active', 'paused', atau 'ended'.
     * @param  string  $reason  Alasan transisi; wajib tidak kosong setelah trim.
     * @return PmSchedule Schedule terkunci dengan state baru.
     *
     * @throws InvalidScheduleTransitionException Transisi tidak legal, reason kosong, state tidak dikenal, atau provenance parent tidak valid.
     */
    public function transition(PmSchedule $schedule, string $target, string $reason): PmSchedule
    {
        // Reason wajib: alasan kosong/blank ditolak fail-closed sebelum
        // transaksi atau mutasi apa pun dibuka.
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidScheduleTransitionException('Alasan transisi lifecycle wajib diisi.');
        }

        return DB::transaction(function () use ($schedule, $target, $reason): PmSchedule {
            // Resolusi Machine dari assignment snapshot caller: Machine induk
            // dikunci lebih dahulu sesuai accepted lock order Machine -> PmSchedule.
            // Parent hilang/terhapus = gagal tertutup.
            $callerAssignment = $schedule->checksheetMachine()->first();
            if ($callerAssignment === null) {
                throw new InvalidScheduleTransitionException('Relasi assignment schedule ke machine tidak ditemukan.');
            }

            $lockedMachine = Machine::query()
                ->whereKey($callerAssignment->machine_id)
                ->lockForUpdate()
                ->first();

            if ($lockedMachine === null) {
                throw new InvalidScheduleTransitionException('Machine parent schedule tidak ditemukan.');
            }

            // Baca ulang dan kunci baris schedule agar keputusan memakai state
            // authoritative terkini, bukan snapshot pemanggil.
            /** @var PmSchedule $locked */
            $locked = PmSchedule::query()->lockForUpdate()->findOrFail($schedule->id);

            // Provenance terkunci: assignment authoritative pada baris schedule
            // yang sudah di-lock harus tetap menunjuk Machine yang sama. Jika
            // assignment berpindah (provenance berubah atau hilang), transisi
            // ditolak fail-closed agar tidak pernah menulis di bawah lock
            // Machine yang salah.
            $authoritativeAssignment = $locked->checksheetMachine()->first();
            if ($authoritativeAssignment === null
                || (int) $authoritativeAssignment->machine_id !== (int) $lockedMachine->id
            ) {
                throw new InvalidScheduleTransitionException(
                    'Provenance assignment schedule berubah atau tidak sesuai dengan machine yang dikunci.'
                );
            }

            $from = (string) $locked->lifecycle_status;

            // Matriks transisi murni dari LifecyclePolicy; state asal/tujuan
            // yang tidak dikenal atau state terminal (ended) ditolak fail-closed.
            if (! $this->lifecyclePolicy->canTransition('schedule', $from, $target)) {
                throw new InvalidScheduleTransitionException(sprintf(
                    "Transisi lifecycle schedule dari '%s' ke '%s' tidak diizinkan.",
                    $from,
                    $target,
                ));
            }

            // Snapshot state lama untuk audit; minimal mengidentifikasi
            // lifecycle_status, is_active, dan effective_live_from.
            $oldValues = $locked->only(['lifecycle_status', 'is_active', 'effective_live_from']);

            $attributes = [
                // Proyeksi kompatibilitas legacy dalam operasi persist yang sama:
                // ACTIVE -> true, PAUSED/ENDED -> false.
                'is_active' => $target === 'active',
                'lifecycle_status' => $target,
            ];

            if ($from === 'paused' && $target === 'active') {
                // Resume menetapkan boundary live baru hari ini (Asia/Jakarta);
                // provenance operational_from/start_date tidak pernah disentuh.
                $attributes['effective_live_from'] = BusinessDate::today();
            }

            $locked->forceFill($attributes)->save();

            // Audit ditulis pada transaksi yang sama dengan mutasi lifecycle:
            // jika penulisan audit gagal, transisi ikut ter-rollback (atomic).
            // Actor diambil dari mekanisme autentikasi/audit yang sudah ada,
            // tanpa tabel audit paralel atau kolom schema baru.
            $this->activityLog->log(
                request: request(),
                moduleName: 'pm_schedule',
                action: 'lifecycle_transition',
                description: $reason,
                tableName: 'pm_schedules',
                recordId: $locked->id,
                oldValues: $oldValues,
                newValues: $locked->only(['lifecycle_status', 'is_active', 'effective_live_from']),
            );

            return $locked;
        });
    }
}
