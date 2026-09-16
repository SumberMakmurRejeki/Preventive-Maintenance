<?php

namespace App\Services\PM;

use App\Models\Machine;
use App\Models\PmSchedule;
use App\Services\Auth\ActivityLogService;
use Illuminate\Support\Facades\DB;

/**
 * Otoritas tunggal mutasi lifecycle Machine untuk transisi ordinary dan retirement.
 *
 * Layanan ini menjaga urutan lock Machine -> PmSchedule, compatibility projection
 * is_active, dan audit atomik tanpa menulis ulang bukti historis PM.
 */
class MachineLifecycleService
{
    public function __construct(
        protected LifecyclePolicy $lifecyclePolicy,
        protected ActivityLogService $activityLog,
        protected ScheduleLifecycleService $scheduleLifecycleService,
    ) {}

    /**
     * Menerapkan satu transisi lifecycle Machine secara transaksional.
     *
     * @throws InvalidMachineTransitionException Jika reason kosong atau transisi tidak legal.
     */
    public function transition(Machine $machine, string $target, string $reason): Machine
    {
        // Reason dari pengguna wajib ada sebelum lock dan mutasi dibuka.
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidMachineTransitionException('Alasan transisi lifecycle wajib diisi.');
        }

        return DB::transaction(function () use ($machine, $target, $reason): Machine {
            // Machine authoritative selalu dikunci lebih dahulu sesuai kontrak lock Slice C.
            /** @var Machine $locked */
            $locked = Machine::query()->lockForUpdate()->findOrFail($machine->id);
            $from = (string) $locked->lifecycle_status;

            if (! $this->lifecyclePolicy->canTransition('machine', $from, $target)) {
                throw new InvalidMachineTransitionException(sprintf(
                    "Transisi lifecycle machine dari '%s' ke '%s' tidak diizinkan.",
                    $from,
                    $target,
                ));
            }

            // Retirement mengakhiri era schedule non-terminal secara atomik setelah
            // seluruh baris schedule dikunci berurutan untuk menghindari deadlock.
            if ($target === 'retired') {
                $schedules = PmSchedule::query()
                    ->whereNull('deleted_at')
                    ->whereHas('checksheetMachine', fn ($query) => $query->where('machine_id', $locked->id))
                    ->whereIn('lifecycle_status', ['active', 'paused'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($schedules as $schedule) {
                    $this->scheduleLifecycleService->transition($schedule, 'ended', $reason);
                }
            }

            $oldValues = $locked->only(['lifecycle_status', 'is_active', 'effective_live_from']);
            $attributes = [
                // is_active tetap hanya compatibility projection dari lifecycle_status.
                'lifecycle_status' => $target,
                'is_active' => $target === 'active',
            ];

            if ($from === 'inactive' && $target === 'active') {
                // Reactivation ordinary menandai boundary materialisasi baru tanpa mengubah histori.
                $attributes['effective_live_from'] = BusinessDate::today();
            }

            $locked->forceFill($attributes)->save();

            // Audit berada dalam transaksi yang sama agar kegagalan audit me-roll back state.
            $this->activityLog->log(
                request: request(),
                moduleName: 'master_mesin',
                action: 'lifecycle_transition',
                description: $reason,
                tableName: 'machines',
                recordId: $locked->id,
                oldValues: $oldValues,
                newValues: $locked->only(['lifecycle_status', 'is_active', 'effective_live_from']),
            );

            return $locked;
        });
    }
}
