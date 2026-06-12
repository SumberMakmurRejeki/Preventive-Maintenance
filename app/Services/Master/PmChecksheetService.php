<?php

namespace App\Services\Master;

use App\Models\PmChecksheet;
use App\Models\PmChecksheetMachine;
use App\Models\PmChecksheetPart;
use App\Models\PmChecksheetStandard;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Services\Auth\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PmChecksheetService
{
    public function __construct(
        protected ActivityLogService $activityLog,
    ) {
    }

    public function create(Request $request, array $payload): PmChecksheet
    {
        return DB::transaction(function () use ($request, $payload): PmChecksheet {
            $checksheet = PmChecksheet::query()->create([
                'checksheet_code' => $payload['checksheet_code'],
                'checksheet_name' => $payload['checksheet_name'],
                'description' => $payload['description'] ?? null,
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'created_by' => $request->user()?->id,
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

            $checksheet->machineAssignments()->delete();

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
        $oldValues = $checksheet->only(['checksheet_code', 'checksheet_name', 'description', 'is_active']);
        $checksheetId = $checksheet->id;
        $checksheetCode = $checksheet->checksheet_code;

        $checksheet->forceDelete();

        $this->activityLog->log(
            request: $request,
            moduleName: 'master_checksheet',
            action: 'delete',
            description: sprintf('Delete checksheet %s', $checksheetCode),
            tableName: 'pm_checksheets',
            recordId: $checksheetId,
            oldValues: $oldValues,
        );
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
        $selectedMachineIds = array_map('intval', $payload['selected_machine_ids']);
        $parts = $payload['parts'];
        $standards = $payload['standards'];
        $schedule = $payload['schedule'];

        foreach ($selectedMachineIds as $machineId) {
            $assignment = PmChecksheetMachine::query()->create([
                'pm_checksheet_id' => $checksheet->id,
                'machine_id' => $machineId,
                'assigned_at' => now(),
                'created_by' => $request->user()?->id,
            ]);

            foreach (($parts[$machineId] ?? []) as $partRow) {
                $part = PmChecksheetPart::query()->create([
                    'pm_checksheet_machine_id' => $assignment->id,
                    'part_name' => $partRow['name'],
                    'description' => $partRow['description'] ?? null,
                    'is_active' => true,
                ]);

                $partKey = (string) ($partRow['id'] ?? '');

                foreach (($standards[$partKey] ?? []) as $standardRow) {
                    PmChecksheetStandard::query()->create([
                        'pm_checksheet_part_id' => $part->id,
                        'standard_name' => $standardRow['name'],
                        'input_type' => $standardRow['input_type'],
                        'action_options' => $standardRow['input_type'] === 'action' ? array_values($standardRow['action_options'] ?? []) : null,
                        'target_value' => $standardRow['input_type'] === 'number' ? $standardRow['target_value'] : null,
                        'min_value' => $standardRow['input_type'] === 'range' ? $standardRow['min_value'] : null,
                        'max_value' => $standardRow['input_type'] === 'range' ? $standardRow['max_value'] : null,
                        'unit' => $standardRow['unit'] ?? null,
                        'description' => $standardRow['description'] ?? null,
                        'is_required' => (bool) ($standardRow['is_required'] ?? true),
                        'is_active' => (bool) ($standardRow['is_active'] ?? true),
                    ]);
                }
            }

            $scheduleModel = PmSchedule::query()->create([
                'pm_checksheet_machine_id' => $assignment->id,
                'frequency_type' => $schedule['frequency_type'],
                'weekly_days' => $schedule['frequency_type'] === 'weekly' ? array_values($schedule['weekly_days']) : null,
                'monthly_day' => $schedule['frequency_type'] === 'monthly' ? $schedule['monthly_day'] : null,
                'start_date' => $schedule['start_date'],
                'generate_until' => $schedule['generate_until'],
                'is_active' => true,
                'created_by' => $request->user()?->id,
            ]);

            $rows = collect($this->generateScheduleDates($schedule))
                ->map(fn (string $date) => [
                    'pm_schedule_id' => $scheduleModel->id,
                    'machine_id' => $machineId,
                    'scheduled_date' => $date,
                    'status' => 'scheduled',
                    'status_changed_at' => null,
                    'generated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($rows !== []) {
                PmScheduleDate::query()->insert($rows);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<int, string>
     */
    protected function generateScheduleDates(array $schedule): array
    {
        $start = Carbon::parse($schedule['start_date'])->startOfDay();
        $end = Carbon::parse($schedule['generate_until'])->startOfDay();

        $dates = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $shouldInclude = false;

            if ($schedule['frequency_type'] === 'daily') {
                $shouldInclude = true;
            }

            if ($schedule['frequency_type'] === 'weekly') {
                $selectedDays = array_map('intval', $schedule['weekly_days'] ?? []);
                $shouldInclude = in_array((int) $cursor->dayOfWeek, $selectedDays, true);
            }

            if ($schedule['frequency_type'] === 'monthly') {
                $targetDay = max(1, min(31, (int) ($schedule['monthly_day'] ?? 1)));
                $validDay = min($targetDay, $cursor->copy()->endOfMonth()->day);
                $shouldInclude = $cursor->day === $validDay;
            }

            if ($shouldInclude) {
                $dates[] = $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return array_values(array_unique($dates));
    }
}
