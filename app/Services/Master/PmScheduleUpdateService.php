<?php

namespace App\Services\Master;

use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmSchedule;
use App\Services\PM\BusinessDate;
use App\Services\PM\PlanningPeriodPolicy;
use App\Services\PM\PmScheduleDateGenerator;
use App\Services\PM\PmScheduleDateReconciler;
use App\Services\PM\StalePreviewException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Layanan preview dan apply untuk update jadwal PM checksheet.
 *
 * Preview: membaca state saat ini, menghitung desired dates dari payload,
 * mengklasifikasi perbedaan, menghasilkan fingerprint, tanpa menulis DB.
 *
 * Apply: lock baris, verifikasi fingerprint cocok, persist config baru,
 * reconcile dates, kembalikan token baru.
 */
class PmScheduleUpdateService
{
    public function __construct(
        protected PmScheduleDateReconciler $reconciler,
        protected PlanningPeriodPolicy $planningPeriodPolicy,
    ) {}

    /**
     * Preview dampak perubahan jadwal tanpa menulis ke database.
     *
     * @param  array<string, mixed>  $schedulePayload  Payload schedule dari wizard.
     * @return array{status: string, impact: array<string, list<string>>, counts: array<string, int>, unresolved: bool, has_conflicts: bool, has_changes: bool, token: string, business_today: string}
     */
    public function preview(PmChecksheet $checksheet, array $schedulePayload): array
    {
        // Hitung tanggal bisnis hari ini (Asia/Jakarta).
        $businessToday = BusinessDate::today();

        // Payload atau schedule tersimpan NULL sama-sama unresolved.
        $hasUnresolvedSchedule = PmSchedule::query()
            ->active()
            ->whereHas(
                'checksheetMachine',
                fn ($query) => $query->where('pm_checksheet_id', $checksheet->id)
            )
            ->whereNull('operational_from')
            ->exists();

        if (empty($schedulePayload['operational_from'] ?? null) || $hasUnresolvedSchedule) {
            return [
                'status' => 'unresolved',
                'unresolved' => true,
                'has_changes' => false,
                'has_conflicts' => false,
                'impact' => [
                    'created' => [],
                    'removed' => [],
                    'retained' => [],
                    'protected' => [],
                    'conflict' => [],
                ],
                'counts' => [
                    'created' => 0,
                    'removed' => 0,
                    'retained' => 0,
                    'protected' => 0,
                    'conflict' => 0,
                ],
                'token' => '',
                'business_today' => $businessToday->toDateString(),
            ];

        }

        // Hitung jendela operasional: start_date dan generate_until.
        $window = $this->resolveWindow($schedulePayload, $businessToday);

        // Hasilkan kumpulan tanggal yang DIINGINKAN dari payload baru.
        $desiredDates = PmScheduleDateGenerator::generate([
            'frequency_type' => $schedulePayload['frequency_type'],
            'weekly_days' => $schedulePayload['weekly_days'] ?? [],
            'monthly_day' => $schedulePayload['monthly_day'] ?? null,
            'start_date' => $window['start_date'],
            'generate_until' => $window['generate_until'],
        ]);
        $desiredSet = array_fill_keys($desiredDates, true);

        // Muat seluruh assignment beserta schedule dan dates (termasuk soft-deleted).
        $assignments = PmChecksheetMachine::query()
            ->where('pm_checksheet_id', $checksheet->id)
            ->with([
                // orderBy scheduled_date agar fingerprint deterministik.
                'schedules.scheduleDates' => function ($query): void {
                    $query->withTrashed()->orderBy('scheduled_date')->with([
                        // orderBy id agar fingerprint deterministik.
                        'executions' => function ($q): void {
                            $q->withTrashed()->orderBy('id');
                        },
                    ]);
                },
            ])
            ->get();

        // Klasifikasi setiap baris tanggal yang ada di database.
        $created = [];
        $removed = [];
        $retained = [];
        $protected = [];
        $conflict = [];

        foreach ($assignments as $assignment) {
            foreach ($assignment->schedules as $schedule) {
                foreach ($schedule->scheduleDates as $dateRow) {
                    $dateStr = $dateRow->scheduled_date->toDateString();
                    $isDesired = isset($desiredSet[$dateStr]);

                    // Aturan proteksi sama dengan reconciler: mutable hanya jika
                    // status di MUTABLE_STATUSES DAN tidak punya eksekusi (termasuk trashed).
                    $isMutable = in_array($dateRow->status, PmScheduleDateReconciler::MUTABLE_STATUSES, true)
                        && $dateRow->executions->isEmpty();

                    if ($isDesired) {
                        // Tanggal diinginkan dan ada di DB.
                        if ($dateRow->trashed()) {
                            if ($isMutable) {
                                // Soft-deleted + mutable -> bisa direstore.
                                $created[] = $dateStr;
                            } else {
                                // Soft-deleted + protected -> konflik (tidak bisa direstore).
                                $conflict[] = $dateStr;
                            }
                        } else {
                            // Tanggal aktif yang diinginkan -> retained (tidak perlu mutasi).
                            $retained[] = $dateStr;
                        }
                    } else {
                        // Tanggal TIDAK diinginkan.
                        if ($isMutable) {
                            $removed[] = $dateStr;
                        } elseif (! $dateRow->trashed()) {
                            // Protected + masih aktif -> dipertahankan.
                            $protected[] = $dateStr;
                        }
                        // Protected + soft-deleted + not desired -> diabaikan (sudah terhapus).
                    }
                }
            }
        }

        // Tanggal yang DIINGINKAN tapi belum ada di DB -> akan dibuat.
        // Evaluasi per schedule karena apply menjalankan reconciler per schedule.
        // Jika Schedule A punya tanggal tapi Schedule B tidak, Schedule B tetap
        // perlu dibuatkan baris baru.
        foreach ($assignments as $assignment) {
            foreach ($assignment->schedules as $schedule) {
                // Kumpulkan semua tanggal yang sudah ada (aktif + soft-deleted)
                // pada schedule ini untuk menghindari duplikat klasifikasi.
                $scheduleDateMap = [];
                foreach ($schedule->scheduleDates as $dateRow) {
                    $scheduleDateMap[$dateRow->scheduled_date->toDateString()] = true;
                }

                foreach ($desiredDates as $dateStr) {
                    if (! isset($scheduleDateMap[$dateStr])) {
                        // Tanggal tidak ada sama sekali pada schedule ini -> perlu dibuat.
                        $created[] = $dateStr;
                    }
                }
            }
        }

        sort($created);
        sort($removed);
        sort($retained);
        sort($protected);
        sort($conflict);

        $hasConflicts = count($conflict) > 0;
        $hasChanges = count($created) + count($removed) > 0;

        // Tentukan status: conflict > normal > zero.
        if ($hasConflicts) {
            $status = 'conflict';
        } elseif ($hasChanges) {
            $status = 'normal';
        } else {
            $status = 'zero';
        }

        return [
            'status' => $status,
            'impact' => [
                'created' => $created,
                'removed' => $removed,
                'retained' => $retained,
                'protected' => $protected,
                'conflict' => $conflict,
            ],
            'counts' => [
                'created' => count($created),
                'removed' => count($removed),
                'retained' => count($retained),
                'protected' => count($protected),
                'conflict' => count($conflict),
            ],
            'unresolved' => $hasConflicts,
            'has_conflicts' => $hasConflicts,
            'has_changes' => $hasChanges,
            // Ikat token dengan payload agar konfirmasi hanya berlaku untuk preview yang sama.
            'token' => $this->fingerprint($checksheet, $businessToday, $schedulePayload),
            'business_today' => $businessToday->toDateString(),
        ];
    }

    /**
     * Hitung fingerprint deterministik atas state tersimpan dan payload preview.
     */
    public function fingerprint(
        PmChecksheet $checksheet,
        Carbon $businessToday,
        array $schedulePayload = [],
    ): string {
        $data = [
            'business_today' => $businessToday->toDateString(),
            'checksheet_id' => $checksheet->id,
            'schedule_payload' => $schedulePayload,
            'machines' => [],
        ];

        $assignments = PmChecksheetMachine::query()
            ->where('pm_checksheet_id', $checksheet->id)
            ->with([
                // orderBy scheduled_date agar fingerprint deterministik.
                'schedules.scheduleDates' => function ($query): void {
                    $query->withTrashed()->orderBy('scheduled_date')->with([
                        // orderBy id agar fingerprint deterministik.
                        'executions' => function ($q): void {
                            $q->withTrashed()->orderBy('id');
                        },
                    ]);
                },
            ])
            ->orderBy('machine_id')
            ->get();

        foreach ($assignments as $assignment) {
            $machineData = [
                'machine_id' => $assignment->machine_id,
                'schedules' => [],
            ];

            foreach ($assignment->schedules as $schedule) {
                $scheduleData = [
                    'id' => $schedule->id,
                    'machine_id' => $assignment->machine_id,
                    'frequency_type' => $schedule->frequency_type,
                    'weekly_days' => $schedule->weekly_days,
                    'monthly_day' => $schedule->monthly_day,
                    'operational_from' => $schedule->operational_from?->toDateString(),
                    'start_date' => $schedule->start_date?->toDateString(),
                    'generate_until' => $schedule->generate_until?->toDateString(),
                    'is_active' => $schedule->is_active,
                    'dates' => [],
                ];

                foreach ($schedule->scheduleDates as $dateRow) {
                    // Bangun data tanggal beserta execution-nya untuk fingerprint.
                    $dateData = [
                        'id' => $dateRow->id,
                        'scheduled_date' => $dateRow->scheduled_date->toDateString(),
                        'status' => $dateRow->status,
                        'deleted_at' => $dateRow->deleted_at?->toDateTimeString(),
                        'executions' => [],
                    ];

                    foreach ($dateRow->executions as $execution) {
                        $dateData['executions'][] = [
                            'id' => $execution->id,
                            'status' => $execution->status,
                            'deleted_at' => $execution->deleted_at?->toDateTimeString(),
                        ];
                    }

                    // Append dateData ke scheduleData setelah semua execution diproses.
                    $scheduleData['dates'][] = $dateData;
                }

                // Append scheduleData ke machineData setelah semua dates diproses.
                $machineData['schedules'][] = $scheduleData;
            }

            // Append machineData ke data setelah semua schedules diproses.
            $data['machines'][] = $machineData;
        }

        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', $json, config('app.key'));
    }

    /**
     * Terapkan perubahan jadwal dengan proteksi transaksi dan locking.
     *
     * Lock pm_checksheets + pm_schedules rows BEFORE re-evaluating fingerprint.
     * Ini mencegah apply membaca state yang berubah antara preview dan apply
     * oleh proses konkuren. Pm_schedule_dates di-lock oleh reconciler saat
     * reconcile().
     *
     * @param  array<string, mixed>  $schedulePayload  Payload schedule dari wizard.
     * @return array{status: string, counts: array<string, int>, has_conflicts: bool, has_changes: bool, token: string}
     *
     * @throws StalePreviewException Jika fingerprint tidak cocok (state berubah).
     */
    public function apply(PmChecksheet $checksheet, array $schedulePayload, string $token): array
    {
        // Tolak apply unresolved sebelum lock, token, transaksi, atau mutasi apa pun.
        if (empty($schedulePayload['operational_from'] ?? null)) {
            return [
                'status' => 'unresolved',
                'message' => 'Tanggal awal operasional wajib diisi sebelum menerapkan jadwal.',
            ];
        }

        return DB::transaction(function () use ($checksheet, $schedulePayload, $token): array {
            // Tangkap tanggal bisnis di dalam transaksi agar konsisten dengan lock.
            $businessToday = BusinessDate::today();
            // Lock baris checksheet untuk mencegah perubahan konkuren.
            $lockedChecksheet = PmChecksheet::query()
                ->lockForUpdate()
                ->findOrFail($checksheet->id);

            // Laravel meneruskan Relations\HasMany untuk eager-load bertingkat,
            // bukan Builder, sehingga closure tidak diberi type-hint Builder.
            $lockedSchedules = PmSchedule::query()
                ->active()
                ->whereHas(
                    'checksheetMachine',
                    fn ($query) => $query->where('pm_checksheet_id', $lockedChecksheet->id)
                )
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // Schedule legacy tetap unresolved meskipun payload membawa tanggal valid.
            if ($lockedSchedules->contains(fn (PmSchedule $schedule): bool => $schedule->operational_from === null)) {
                return [
                    'status' => 'unresolved',
                    'message' => 'Jadwal tersimpan tanpa tanggal awal operasional belum dapat diterapkan.',
                ];
            }

            // Hitung ulang fingerprint dari state yang sudah di-lock.
            $currentFingerprint = $this->fingerprint($lockedChecksheet, $businessToday, $schedulePayload);

            // Verifikasi token cocok. Jika tidak, state berubah antara preview
            // dan apply -> lempar StalePreviewException.
            if (! hash_equals($token, $currentFingerprint)) {
                throw new StalePreviewException;
            }

            // Persist konfigurasi schedule baru (upsert assignment, schedule).
            $this->persistScheduleConfig($lockedChecksheet, $schedulePayload, $businessToday);

            // Laravel meneruskan Relations\HasMany untuk eager-load bertingkat,
            // bukan Builder, sehingga closure tidak diberi type-hint Builder.
            $schedules = PmSchedule::query()
                ->active()
                ->whereHas(
                    'checksheetMachine',
                    fn ($query) => $query->where('pm_checksheet_id', $lockedChecksheet->id)
                )
                ->orderBy('id', 'asc')
                ->get();

            // Reconcile setiap schedule: lock dates, bandingkan dengan desired,
            // terapkan mutasi (create/restore/remove).
            $totalCounts = [
                'examined' => 0,
                'created' => 0,
                'restored' => 0,
                'removed' => 0,
                'unchanged' => 0,
                'conflicts' => 0,
            ];

            foreach ($schedules as $schedule) {
                $result = $this->reconciler->reconcile($schedule);

                foreach ($totalCounts as $key => $value) {
                    $totalCounts[$key] = $value + $result[$key];
                }
            }

            // Hitung ulang fingerprint baru setelah mutasi.
            $freshToken = $this->fingerprint($lockedChecksheet->fresh(), $businessToday, $schedulePayload);

            return [
                'status' => 'applied',
                'counts' => $totalCounts,
                'has_conflicts' => $totalCounts['conflicts'] > 0,
                'has_changes' => $totalCounts['created'] + $totalCounts['restored'] + $totalCounts['removed'] > 0,
                'token' => $freshToken,
            ];
        });
    }

    /**
     * Hitung jendela operasional dari payload schedule.
     *
     * Logika sama dengan PmChecksheetService::resolveScheduleWindow():
     * - operational_from ada -> start_date = operational_from,
     *   generate_until = planning_start + initialMonths.
     * - operational_from kosong -> fallback ke start_date/generate_until
     *   dari payload (jalur legacy).
     *
     * @param  array<string, mixed>  $schedulePayload
     * @return array{start_date: string|null, generate_until: string|null}
     */
    private function resolveWindow(array $schedulePayload, Carbon $businessToday): array
    {
        $operationalFrom = $schedulePayload['operational_from'] ?? null;

        if (! empty($operationalFrom)) {
            $operationalFromDate = Carbon::parse($operationalFrom)->startOfDay();
            $window = $this->planningPeriodPolicy->planningWindow($businessToday, $operationalFromDate);

            return [
                'start_date' => $operationalFromDate->toDateString(),
                'generate_until' => $window['planning_end']->toDateString(),
            ];
        }

        // Jalur legacy: gunakan start_date/generate_until dari payload.
        return [
            'start_date' => $schedulePayload['start_date'] ?? null,
            'generate_until' => $schedulePayload['generate_until'] ?? null,
        ];
    }

    /**
     * Persist konfigurasi schedule ke database (upsert assignment + schedule).
     *
     * Logika sama dengan PmChecksheetService::syncWizardData() bagian schedule,
     * tanpa memerlukan Request object.
     *
     * @param  array<string, mixed>  $schedulePayload
     */
    private function persistScheduleConfig(PmChecksheet $checksheet, array $schedulePayload, Carbon $businessToday): void
    {
        $selectedMachineIds = array_map('intval', $checksheet->machineAssignments()->pluck('machine_id')->all());
        $window = $this->resolveWindow($schedulePayload, $businessToday);

        foreach ($selectedMachineIds as $machineId) {
            $assignment = PmChecksheetMachine::query()->updateOrCreate(
                [
                    'pm_checksheet_id' => $checksheet->id,
                    'machine_id' => $machineId,
                ],
                [
                    'assigned_at' => now(),
                ],
            );

            PmSchedule::query()->updateOrCreate(
                ['pm_checksheet_machine_id' => $assignment->id],
                [
                    'frequency_type' => $schedulePayload['frequency_type'],
                    'weekly_days' => $schedulePayload['frequency_type'] === 'weekly'
                        ? array_values($schedulePayload['weekly_days'] ?? [])
                        : null,
                    'monthly_day' => $schedulePayload['frequency_type'] === 'monthly'
                        ? $schedulePayload['monthly_day']
                        : null,
                    'operational_from' => $window['start_date'],
                    'start_date' => $window['start_date'],
                    'generate_until' => $window['generate_until'],
                    'is_active' => true,
                ],
            );
        }
    }
}
