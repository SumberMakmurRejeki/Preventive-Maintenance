<?php

namespace App\Services\Master;

use App\Models\Machine;
use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmChecksheetPart;
use App\Models\PmChecksheetStandard;
use App\Models\PmSchedule;
use App\Services\Auth\ActivityLogService;
use App\Services\PM\BusinessDate;
use App\Services\PM\PlanningPeriodPolicy;
use App\Services\PM\PmScheduleDateReconciler;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PmChecksheetService
{
    public function __construct(
        protected ActivityLogService $activityLog,
        protected PmScheduleDateReconciler $scheduleDateReconciler,
        protected PlanningPeriodPolicy $planningPeriodPolicy,
    ) {}

    public function create(Request $request, array $payload): PmChecksheet
    {
        return DB::transaction(function () use ($request, $payload): PmChecksheet {
            $checksheet = PmChecksheet::query()->create([
                'checksheet_code' => $payload['checksheet_code'],
                'checksheet_name' => $payload['checksheet_name'],
                'description' => $payload['description'] ?? null,
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'created_by' => $request->user()?->id,
                'assignment_history_known' => true,
            ]);

            $this->syncWizardData($checksheet, $payload, $request);

            $this->activityLog->log(
                request: $request,
                moduleName: 'master_checksheet',
                action: 'create',
                description: sprintf('Create checksheet %s', $checksheet->checksheet_code),
                tableName: 'pm_checksheets',
                recordId: $checksheet->id,
                newValues: $checksheet->only(['checksheet_code', 'checksheet_name', 'description', 'is_active']),
            );

            return $checksheet->fresh(['machineAssignments.parts.standards', 'machineAssignments.schedules.scheduleDates']);
        });
    }

    public function update(Request $request, PmChecksheet $checksheet, array $payload): PmChecksheet
    {
        return DB::transaction(function () use ($request, $checksheet, $payload): PmChecksheet {
            $oldValues = $checksheet->only(['checksheet_code', 'checksheet_name', 'description', 'is_active']);

            $checksheet->fill([
                'checksheet_code' => $payload['checksheet_code'],
                'checksheet_name' => $payload['checksheet_name'],
                'description' => $payload['description'] ?? null,
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ])->save();

            $this->syncWizardData($checksheet, $payload, $request);

            $this->activityLog->log(
                request: $request,
                moduleName: 'master_checksheet',
                action: 'update',
                description: sprintf('Update checksheet %s', $checksheet->checksheet_code),
                tableName: 'pm_checksheets',
                recordId: $checksheet->id,
                oldValues: $oldValues,
                newValues: $checksheet->only(['checksheet_code', 'checksheet_name', 'description', 'is_active']),
            );

            return $checksheet->fresh(['machineAssignments.parts.standards', 'machineAssignments.schedules.scheduleDates']);
        });
    }

    public function deactivate(Request $request, PmChecksheet $checksheet): void
    {
        $oldValues = $checksheet->only(['checksheet_code', 'checksheet_name', 'description', 'is_active']);

        $checksheet->forceFill(['is_active' => false])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_checksheet',
            action: 'deactivate',
            description: sprintf('Deactivate checksheet %s', $checksheet->checksheet_code),
            tableName: 'pm_checksheets',
            recordId: $checksheet->id,
            oldValues: $oldValues,
            newValues: $checksheet->only(['checksheet_code', 'checksheet_name', 'description', 'is_active']),
        );
    }

    public function activate(Request $request, PmChecksheet $checksheet): void
    {
        $oldValues = $checksheet->only(['checksheet_code', 'checksheet_name', 'description', 'is_active']);

        $checksheet->forceFill(['is_active' => true])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_checksheet',
            action: 'activate',
            description: sprintf('Activate checksheet %s', $checksheet->checksheet_code),
            tableName: 'pm_checksheets',
            recordId: $checksheet->id,
            oldValues: $oldValues,
            newValues: $checksheet->only(['checksheet_code', 'checksheet_name', 'description', 'is_active']),
        );
    }

    public function delete(Request $request, PmChecksheet $checksheet): void
    {
        DB::transaction(function () use ($request, $checksheet): void {
            // Re-read dan kunci baris di bawah transaksi agar keputusan eligibility
            // tidak berkompetisi dengan operasi assignment concurrent.
            $locked = PmChecksheet::withTrashed()
                ->whereKey($checksheet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldValues = $locked->only(['checksheet_code', 'checksheet_name', 'description', 'is_active']);
            $checksheetId = $locked->id;
            $checksheetCode = $locked->checksheet_code;

            // Guard 1: masih memiliki assignment aktif saat ini.
            if ($locked->machineAssignments()->exists()) {
                throw new DomainException(
                    'PM Checksheet yang sudah terhubung ke mesin tidak dapat dihapus. '
                    .'Nonaktifkan checksheet jika tidak lagi digunakan.'
                );
            }

            // Guard 2: legacy row — riwayat assignment tidak dapat dipastikan.
            if (! $locked->assignment_history_known) {
                throw new DomainException(
                    'Data operasional lama tidak dapat dihapus permanen karena riwayat penggunaannya '
                    .'tidak dapat dipastikan. Nonaktifkan checksheet jika tidak lagi digunakan.'
                );
            }

            // Guard 3: pernah memiliki assignment (marker terisi) — histori mungkin ada.
            if ($locked->first_observed_machine_assignment_at !== null) {
                throw new DomainException(
                    'PM Checksheet yang sudah terhubung ke mesin tidak dapat dihapus. '
                    .'Nonaktifkan checksheet jika tidak lagi digunakan.'
                );
            }

            // Kondisi lolos: history_known=true, belum pernah punya assignment, tidak ada assignment kini.
            $locked->forceDelete();

            $this->activityLog->log(
                request: $request,
                moduleName: 'master_checksheet',
                action: 'delete',
                description: sprintf('Delete checksheet %s', $checksheetCode),
                tableName: 'pm_checksheets',
                recordId: $checksheetId,
                oldValues: $oldValues,
            );
        });
    }

    /**
     * @return array{selected: int, schedules: list<array{schedule: PmSchedule, result: array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int}}>, totals: array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int}, has_conflicts: bool, has_changes: bool}
     */
    public function previewScheduleDates(PmChecksheet $checksheet): array
    {
        return $this->aggregateSchedulePreviews($this->activeSchedulesFor($checksheet->id)->get());
    }

    /**
     * @return array{selected: int, schedules: list<array{schedule: PmSchedule, result: array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int}}>, totals: array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int}, has_conflicts: bool, has_changes: bool}
     */
    public function reconcileScheduleDates(Request $request, PmChecksheet $checksheet): array
    {
        return DB::transaction(function () use ($request, $checksheet): array {
            $lockedChecksheet = PmChecksheet::query()
                ->lockForUpdate()
                ->findOrFail($checksheet->id);

            if (! $lockedChecksheet->is_active) {
                throw new DomainException('Checksheet nonaktif tidak dapat disinkronkan.');
            }

            // TASK-003 Slice 3: Ambil ID mesin yang memiliki jadwal aktif saja
            // (bukan semua assignment) untuk menghindari lock berlebihan
            $machineIds = PmChecksheetMachine::query()
                ->where('pm_checksheet_id', $lockedChecksheet->id)
                ->whereHas('schedules', fn ($q) => $q->active())
                ->orderBy('machine_id', 'asc')
                ->pluck('machine_id')
                ->unique()
                ->values()
                ->all();

            // TASK-003 Slice 3: Kunci mesin berurutan ascending SEBELUM jadwal
            if (! empty($machineIds)) {
                Machine::query()
                    ->whereIn('id', $machineIds)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();
            }

            // NOW lock schedules (after machines are locked)
            $schedules = $this->activeSchedulesFor($lockedChecksheet->id)
                ->lockForUpdate()
                ->get();

            if ($schedules->isEmpty()) {
                throw new DomainException('Tidak ada jadwal PM aktif untuk disinkronkan.');
            }

            $preview = $this->aggregateSchedulePreviews($schedules);

            // Cek unresolved schedule sebelum has_conflicts/has_changes
            if ($preview['unresolved'] ?? false) {
                throw new DomainException('Terdapat jadwal dengan tanggal operasional yang belum ditetapkan. Silakan tetapkan tanggal operasional terlebih dahulu.');
            }

            if ($preview['has_conflicts']) {
                throw new DomainException('Sinkronisasi dibatalkan karena terdapat konflik pada tanggal yang sudah terlindungi.');
            }

            if (! $preview['has_changes']) {
                throw new DomainException('Tidak ada perubahan jadwal yang dapat diterapkan.');
            }

            $actualTotals = [
                'examined' => 0,
                'created' => 0,
                'restored' => 0,
                'removed' => 0,
                'unchanged' => 0,
                'conflicts' => 0,
            ];

            foreach ($schedules as $schedule) {
                $result = $this->scheduleDateReconciler->reconcile($schedule);

                foreach ($actualTotals as $counter => $total) {
                    $actualTotals[$counter] = $total + $result[$counter];
                }
            }

            if ($actualTotals['conflicts'] > 0) {
                throw new DomainException('Sinkronisasi dibatalkan karena terjadi konflik saat penerapan perubahan.');
            }

            $this->activityLog->log(
                request: $request,
                moduleName: 'master_checksheet',
                action: 'reconcile_schedule_dates',
                description: sprintf('Reconcile schedule dates for checksheet %s', $lockedChecksheet->checksheet_code),
                tableName: 'pm_checksheets',
                recordId: $lockedChecksheet->id,
                newValues: [
                    'selected' => $preview['selected'],
                    ...$actualTotals,
                ],
            );

            return [
                ...$preview,
                'totals' => $actualTotals,
                'has_conflicts' => false,
                'has_changes' => $actualTotals['created'] + $actualTotals['restored'] + $actualTotals['removed'] > 0,
            ];
        });
    }

    public function toWizardPayload(PmChecksheet $checksheet): array
    {
        $selectedMachineIds = [];
        $parts = [];
        $standards = [];
        $schedule = null;

        foreach ($checksheet->machineAssignments as $assignment) {
            $machineId = (int) $assignment->machine_id;
            $selectedMachineIds[] = $machineId;
            $parts[$machineId] = [];

            foreach ($assignment->parts as $part) {
                $partId = (string) $part->id;
                $parts[$machineId][] = [
                    'id' => $partId,
                    'name' => $part->part_name,
                    'description' => $part->description,
                ];

                $standards[$partId] = $part->standards->map(function (PmChecksheetStandard $standard): array {
                    return [
                        'name' => $standard->standard_name,
                        'input_type' => $standard->input_type,
                        'action_options' => $standard->action_options ?? [],
                        'target_value' => $standard->target_value,
                        'min_value' => $standard->min_value,
                        'max_value' => $standard->max_value,
                        'unit' => $standard->unit,
                        'description' => $standard->description,
                        'is_required' => (bool) $standard->is_required,
                        'is_active' => (bool) $standard->is_active,
                    ];
                })->all();
            }

            if ($schedule === null) {
                $assignmentSchedule = $assignment->schedules->first();

                if ($assignmentSchedule) {
                    $schedule = [
                        'frequency_type' => $assignmentSchedule->frequency_type,
                        'weekly_days' => $assignmentSchedule->weekly_days ?? [],
                        'monthly_day' => $assignmentSchedule->monthly_day,
                        'operational_from' => optional($assignmentSchedule->operational_from)->toDateString(),
                        'start_date' => optional($assignmentSchedule->start_date)->toDateString(),
                        'generate_until' => optional($assignmentSchedule->generate_until)->toDateString(),
                    ];
                }
            }
        }

        return [
            'selected_machine_ids' => $selectedMachineIds,
            'parts' => $parts,
            'standards' => $standards,
            'schedule' => $schedule,
        ];
    }

    protected function syncWizardData(PmChecksheet $checksheet, array $payload, Request $request): void
    {
        // Kunci Checksheet terlebih dahulu untuk menjaga urutan lock konsisten
        // (Checksheet → Machine ascending) dan mencegah deadlock.
        // Gunakan instance terkunci sebagai sumber kebenaran untuk marker decision.
        $lockedChecksheet = PmChecksheet::query()
            ->whereKey($checksheet->id)
            ->lockForUpdate()
            ->firstOrFail();

        // Normalisasi ID lalu kunci semua Machine sebelum mutation child/config.
        $selectedMachineIds = array_values(array_unique(array_map('intval', $payload['selected_machine_ids'])));
        sort($selectedMachineIds, SORT_NUMERIC);
        Machine::query()
            ->whereIn('id', $selectedMachineIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        $parts = $payload['parts'];
        $standards = $payload['standards'];
        $schedule = $payload['schedule'];

        foreach ($selectedMachineIds as $machineId) {
            $assignment = PmChecksheetMachine::query()->updateOrCreate(
                [
                    'pm_checksheet_id' => $checksheet->id,
                    'machine_id' => $machineId,
                ],
                [
                    'assigned_at' => now(),
                    'created_by' => $request->user()?->id,
                ],
            );

            // Stamp marker pertama kali saat assignment baru benar-benar dibuat,
            // berlaku untuk semua checksheet — termasuk legacy (assignment_history_known=false).
            // Gunakan nilai dari instance terkunci agar tidak bergantung state in-memory.
            // Legacy row tetap dilindungi Guard 2 dari deletion meski marker sudah terisi.
            if ($assignment->wasRecentlyCreated
                && $lockedChecksheet->first_observed_machine_assignment_at === null
            ) {
                $lockedChecksheet->forceFill(['first_observed_machine_assignment_at' => now()])->save();
                // Perbarui juga instance yang beredar agar iterasi berikutnya tidak double-stamp.
                $checksheet->first_observed_machine_assignment_at = $lockedChecksheet->first_observed_machine_assignment_at;
            }

            foreach (($parts[$machineId] ?? []) as $partRow) {
                $part = PmChecksheetPart::query()->updateOrCreate(
                    [
                        'pm_checksheet_machine_id' => $assignment->id,
                        'part_name' => $partRow['name'],
                    ],
                    [
                        'description' => $partRow['description'] ?? null,
                        'is_active' => true,
                    ],
                );

                $partKey = (string) ($partRow['id'] ?? '');

                foreach (($standards[$partKey] ?? []) as $standardRow) {
                    PmChecksheetStandard::query()->updateOrCreate(
                        [
                            'pm_checksheet_part_id' => $part->id,
                            'standard_name' => $standardRow['name'],
                        ],
                        [
                            'input_type' => $standardRow['input_type'],
                            'action_options' => $standardRow['input_type'] === 'action' ? array_values($standardRow['action_options'] ?? []) : null,
                            'target_value' => $standardRow['input_type'] === 'number' ? $standardRow['target_value'] : null,
                            'min_value' => $standardRow['input_type'] === 'range' ? $standardRow['min_value'] : null,
                            'max_value' => $standardRow['input_type'] === 'range' ? $standardRow['max_value'] : null,
                            'unit' => $standardRow['unit'] ?? null,
                            'description' => $standardRow['description'] ?? null,
                            'is_required' => (bool) ($standardRow['is_required'] ?? true),
                            'is_active' => (bool) ($standardRow['is_active'] ?? true),
                        ],
                    );
                }
            }

            $existingSchedule = PmSchedule::query()
                ->where('pm_checksheet_machine_id', $assignment->id)
                ->first();

            // Tentukan jendela operasional:
            // - Jika payload membawa operational_from (jalur baru) -> hitung
            //   start_date (batas operasional) dan generate_until (akhir window).
            // - Jika kosong (jalur legacy) -> pertahankan nilai start_date /
            //   generate_until dari jadwal lama, dengan cadangan terakhir ke
            //   kunci legacy payload untuk jadwal yang baru dibuat.
            $window = $this->resolveScheduleWindow($existingSchedule, $schedule);

            $scheduleModel = PmSchedule::query()->updateOrCreate(
                ['pm_checksheet_machine_id' => $assignment->id],
                [
                    'frequency_type' => $schedule['frequency_type'],
                    'weekly_days' => $schedule['frequency_type'] === 'weekly' ? array_values($schedule['weekly_days']) : null,
                    'monthly_day' => $schedule['frequency_type'] === 'monthly' ? $schedule['monthly_day'] : null,
                    'operational_from' => $window['operational_from'],
                    'start_date' => $window['start_date'],
                    'generate_until' => $window['generate_until'],
                    'is_active' => true,
                    'created_by' => $request->user()?->id,
                ],
            );

            $this->scheduleDateReconciler->reconcile($scheduleModel);
        }
    }

    /**
     * Hitung jendela operasional untuk satu jadwal.
     *
     * @param  array<string, mixed>  $schedule
     * @return array{operational_from: string|null, start_date: string|null, generate_until: string|null}
     */
    protected function resolveScheduleWindow(?PmSchedule $existingSchedule, array $schedule): array
    {
        $operationalFrom = $schedule['operational_from'] ?? null;

        if (! empty($operationalFrom)) {
            $operationalFromDate = Carbon::parse($operationalFrom)->startOfDay();
            $businessToday = BusinessDate::today();

            // planning_start = max(business_today, operational_from);
            // planning_end   = planning_start + initialMonths (no-overflow);
            // generate_until = planning_end (inklusif), dihitung backend-side.
            $window = $this->planningPeriodPolicy->planningWindow($businessToday, $operationalFromDate);

            // start_date (batas operasional) HARUS sama dengan operational_from,
            // bukan planning_start. planning_start = max(business_today,
            // operational_from) hanya menjadi batas bawah GENERATION (mengisi
            // generate_until), bukan nilai yang dipersist pada start_date.
            // Dengan ini, pada jalur update dengan operational_from di masa lalu,
            // start_date tetap mengikuti operational_from (kontrak TASK-002).
            return [
                'operational_from' => $operationalFromDate->toDateString(),
                'start_date' => $operationalFromDate->toDateString(),
                'generate_until' => $window['planning_end']->toDateString(),
            ];
        }

        // Jalur legacy: pertahankan nilai jadwal lama, atau fallback ke kunci
        // legacy payload saat membuat jadwal baru (kompatibilitas defensif).
        return [
            'operational_from' => $existingSchedule?->operational_from?->toDateString(),
            'start_date' => $existingSchedule?->start_date?->toDateString() ?? $schedule['start_date'] ?? null,
            'generate_until' => $existingSchedule?->generate_until?->toDateString() ?? $schedule['generate_until'] ?? null,
        ];
    }

    /** @return Builder<PmSchedule> */
    private function activeSchedulesFor(int $checksheetId): Builder
    {
        return PmSchedule::query()
            ->active()
            ->whereHas('checksheetMachine', fn (Builder $query) => $query->where('pm_checksheet_id', $checksheetId))
            ->with('checksheetMachine.machine')
            ->orderBy('id');
    }

    /**
     * @param  iterable<PmSchedule>  $schedules
     * @return array{selected: int, schedules: list<array{schedule: PmSchedule, result: array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int}}>, totals: array{examined: int, created: int, restored: int, removed: int, unchanged: int, conflicts: int}, has_conflicts: bool, has_changes: bool}
     */
    private function aggregateSchedulePreviews(iterable $schedules): array
    {
        $totals = [
            'examined' => 0,
            'created' => 0,
            'restored' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'conflicts' => 0,
        ];
        $rows = [];
        $hasUnresolved = false;

        foreach ($schedules as $schedule) {
            $result = $this->scheduleDateReconciler->preview($schedule);
            $hasUnresolved = $hasUnresolved
                || $schedule->operational_from === null
                || ($result['unresolved'] ?? false);

            foreach ($totals as $counter => $total) {

                $totals[$counter] = $total + $result[$counter];
            }

            $rows[] = [
                'schedule' => $schedule,
                'result' => $result,
            ];
        }

        $result = [
            'selected' => count($rows),
            'schedules' => $rows,
            'totals' => $totals,
            'has_conflicts' => $totals['conflicts'] > 0,
            'has_changes' => $totals['created'] + $totals['restored'] + $totals['removed'] > 0,
        ];

        return $hasUnresolved ? [...$result, 'unresolved' => true] : $result;
    }
}
