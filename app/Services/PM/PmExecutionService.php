<?php

namespace App\Services\PM;

use App\Models\Machine;
use App\Models\PmChecksheetPart;
use App\Models\PmExecution;
use App\Models\PmExecutionItem;
use App\Models\PmExecutionMedia;
use App\Models\PmSchedule;
use App\Models\PmScheduleDate;
use App\Models\User;
use App\Services\Auth\ActivityLogService;
use App\Services\Notification\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmExecutionService
{
    public function __construct(
        protected PmWarningService $warningService,
        protected PmExecutionMediaService $mediaService,
        protected ActivityLogService $activityLogService,
        protected AdminNotificationService $notificationService,
    ) {}

    /**
     * @return array{
     *   schedule_date: PmScheduleDate,
     *   execution: ?PmExecution,
     *   checksheet_code: string,
     *   checksheet_name: string,
     *   parts: Collection<int, PmChecksheetPart>,
     *   existing_items: array<int, PmExecutionItem>,
     *   media_by_part: array<int, Collection<int, PmExecutionMedia>>
     * }
     *
     * @throws ValidationException
     */
    public function getExecutorContext(Machine $machine): array
    {
        $scheduleDate = $this->findScheduleDateForExecutor($machine);
        $execution = $this->findInProgressExecution($machine, $scheduleDate);
        $checksheetMachine = $scheduleDate->schedule?->checksheetMachine;

        if (! $checksheetMachine) {
            throw ValidationException::withMessages([
                'machine' => 'Relasi checksheet mesin tidak ditemukan.',
            ]);
        }

        $parts = PmChecksheetPart::query()
            ->where('pm_checksheet_machine_id', $checksheetMachine->id)
            ->where('is_active', true)
            ->with([
                'standards' => fn ($query) => $query->where('is_active', true)->orderBy('id'),
            ])
            ->orderBy('id')
            ->get();

        if ($parts->isEmpty()) {
            throw ValidationException::withMessages([
                'machine' => 'Part atau standard checksheet belum tersedia.',
            ]);
        }

        $existingItems = $execution
            ? PmExecutionItem::query()
                ->where('pm_execution_id', $execution->id)
                ->get()
                ->keyBy('pm_checksheet_standard_id')
                ->all()
            : [];

        $mediaByPart = $execution
            ? $execution->media()
                ->orderByDesc('id')
                ->get()
                ->groupBy('pm_checksheet_part_id')
                ->all()
            : [];

        return [
            'schedule_date' => $scheduleDate,
            'execution' => $execution,
            'checksheet_code' => (string) ($checksheetMachine->checksheet?->checksheet_code ?? '-'),
            'checksheet_name' => (string) ($checksheetMachine->checksheet?->checksheet_name ?? '-'),
            'parts' => $parts,
            'existing_items' => $existingItems,
            'media_by_part' => $mediaByPart,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $actionValues
     * @param  array<int|string, mixed>  $numberValues
     * @param  array<int|string, mixed>  $partNotes
     * @param  array<int|string, array<int, UploadedFile>>  $mediaFiles
     *
     * @throws ValidationException
     */
    public function startOrSaveDraft(
        Request $request,
        Machine $machine,
        User $operator,
        array $actionValues,
        array $numberValues,
        array $partNotes,
        array $mediaFiles = [],
    ): PmExecution {
        return DB::transaction(function () use ($request, $machine, $operator, $actionValues, $numberValues, $partNotes, $mediaFiles): PmExecution {
            // Resolver identity selalu membaca ulang parent dan occurrence dari database;
            // context dari halaman/controller hanya snapshot dan tidak menjadi sumber keputusan.
            $context = $this->getExecutorContext($machine);
            $scheduleDate = $context['schedule_date'];

            if (! in_array($scheduleDate->status, ['scheduled', 'overdue', 'in_progress'], true)) {
                throw ValidationException::withMessages([
                    'machine' => 'Jadwal PM tidak dalam status yang dapat dikerjakan.',
                ]);
            }

            $execution = $this->startExecutionOnly($request, $machine, $operator, $context);

            // Kunci execution tetap dipertahankan sampai transaction outer selesai agar
            // mutation item/media tidak berjalan terhadap lifecycle yang sudah berubah.
            $lockedExecution = PmExecution::query()->lockForUpdate()->findOrFail($execution->id);

            // Verifikasi status tersimpan sebelum mutasi draft apa pun.
            if ($lockedExecution->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'machine' => 'PM sudah tidak dalam status aktif; submit ditolak.',
                ]);
            }

            $execution = $lockedExecution;

            $this->persistExecutionItems($execution, $context['parts'], $actionValues, $numberValues, $partNotes);
            $this->persistMediaFiles($execution, $context['parts'], $mediaFiles, $operator, $partNotes);

            return $execution->fresh(['items', 'media']) ?? $execution;
        });
    }

    /**
     * @param  array<int|string, mixed>  $actionValues
     * @param  array<int|string, mixed>  $numberValues
     * @param  array<int|string, mixed>  $partNotes
     * @param  array<int|string, array<int, UploadedFile>>  $mediaFiles
     *
     * @throws ValidationException
     */
    public function submit(
        Request $request,
        Machine $machine,
        User $operator,
        array $actionValues,
        array $numberValues,
        array $partNotes,
        array $mediaFiles = [],
    ): PmExecution {
        return DB::transaction(function () use ($request, $machine, $operator, $actionValues, $numberValues, $partNotes, $mediaFiles): PmExecution {
            $execution = $this->startOrSaveDraft(
                request: $request,
                machine: $machine,
                operator: $operator,
                actionValues: $actionValues,
                numberValues: $numberValues,
                partNotes: $partNotes,
                mediaFiles: $mediaFiles,
            );

            if ($execution->status === 'waiting_review') {
                throw ValidationException::withMessages([
                    'machine' => 'PM sudah disubmit dan menunggu review.',
                ]);
            }

            $execution->forceFill([
                'status' => 'waiting_review',
                'submitted_at' => now(),
            ])->save();

            $execution->scheduleDate?->forceFill([
                'status' => 'waiting_review',
                'status_changed_at' => now(),
            ])->save();

            if ($execution->scheduleDate) {
                $this->ensureNextScheduleDateExists($execution->scheduleDate);
            }

            $this->notificationService->createUnique(
                notificationType: 'pm_waiting_review',
                title: sprintf(
                    'Menunggu Review: Hasil PM Mesin %s',
                    $machine->machine_name,
                ),
                message: sprintf(
                    'Hai, proses Preventive Maintenance untuk mesin %s telah selesai dikerjakan. Mohon segera melakukan review agar proses ini dapat segera diselesaikan. Terima kasih atas kerja samanya!',
                    $machine->machine_name,
                ),
                relatedTable: 'pm_executions',
                relatedId: $execution->id,
                targetUrl: '/pm/review/'.$execution->id,
                data: [
                    'machine_id' => $machine->id,
                    'machine_code' => $machine->machine_code,
                ],
            );

            $this->activityLogService->log(
                request: $request,
                moduleName: 'pm_executor',
                action: 'submit_pm',
                description: sprintf('Submit PM untuk mesin %s', $machine->machine_code),
                tableName: 'pm_executions',
                recordId: $execution->id,
            );

            return $execution->fresh(['items', 'media']) ?? $execution;
        });
    }

    /**
     * @param  array<int|string, mixed>  $actionValues
     * @param  array<int|string, mixed>  $numberValues
     * @param  array<int|string, mixed>  $partNotes
     *
     * @throws ValidationException
     */
    protected function persistExecutionItems(
        PmExecution $execution,
        Collection $parts,
        array $actionValues,
        array $numberValues,
        array $partNotes,
    ): void {
        $rows = [];

        foreach ($parts as $part) {
            foreach ($part->standards as $standard) {
                $actionValue = isset($actionValues[$standard->id]) ? strtoupper(trim((string) $actionValues[$standard->id])) : null;
                $numberValue = isset($numberValues[$standard->id]) && $numberValues[$standard->id] !== ''
                    ? (float) $numberValues[$standard->id]
                    : null;

                if ($standard->input_type === 'action') {
                    $options = collect($standard->action_options ?? [])->map(fn ($option) => strtoupper((string) $option))->all();

                    if ($standard->is_required && ($actionValue === null || $actionValue === '')) {
                        throw ValidationException::withMessages([
                            "execution_action.{$standard->id}" => sprintf('Standard "%s" wajib diisi.', $standard->standard_name),
                        ]);
                    }

                    if ($actionValue && ! in_array($actionValue, $options, true)) {
                        throw ValidationException::withMessages([
                            "execution_action.{$standard->id}" => sprintf('Pilihan action "%s" tidak valid.', $standard->standard_name),
                        ]);
                    }
                }

                if (in_array($standard->input_type, ['number', 'range'], true)) {
                    if ($standard->is_required && $numberValue === null) {
                        throw ValidationException::withMessages([
                            "execution_number.{$standard->id}" => sprintf('Standard "%s" wajib diisi angka.', $standard->standard_name),
                        ]);
                    }
                }

                $warning = $this->warningService->evaluate([
                    'input_type' => $standard->input_type,
                    'target_value' => $standard->target_value,
                    'min_value' => $standard->min_value,
                    'max_value' => $standard->max_value,
                    'unit' => $standard->unit,
                ], $numberValue);

                $rows[] = [
                    'pm_execution_id' => $execution->id,
                    'pm_checksheet_part_id' => $part->id,
                    'pm_checksheet_standard_id' => $standard->id,
                    'part_name_snapshot' => $part->part_name,
                    'standard_name_snapshot' => $standard->standard_name,
                    'input_type_snapshot' => $standard->input_type,
                    'action_options_snapshot' => $standard->action_options ? json_encode($standard->action_options, JSON_THROW_ON_ERROR) : null,
                    'target_value_snapshot' => $standard->target_value,
                    'min_value_snapshot' => $standard->min_value,
                    'max_value_snapshot' => $standard->max_value,
                    'unit_snapshot' => $standard->unit,
                    'action_value' => $actionValue,
                    'number_value' => $numberValue,
                    'is_warning' => $warning['is_warning'],
                    'warning_message' => $warning['warning_message'],
                    'note' => isset($partNotes[$part->id]) ? trim((string) $partNotes[$part->id]) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        PmExecutionItem::query()->where('pm_execution_id', $execution->id)->delete();
        PmExecutionItem::query()->insert($rows);
    }

    /**
     * @param  array<int|string, array<int, UploadedFile>>  $mediaFiles
     * @param  array<int|string, mixed>  $partNotes
     *
     * @throws ValidationException
     */
    protected function persistMediaFiles(
        PmExecution $execution,
        Collection $parts,
        array $mediaFiles,
        User $operator,
        array $partNotes,
    ): void {
        $partsById = $parts->keyBy('id');

        foreach ($mediaFiles as $partId => $files) {
            $part = $partsById->get((int) $partId);

            if (! $part) {
                throw ValidationException::withMessages([
                    'media_files' => 'Part media tidak valid.',
                ]);
            }

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $this->mediaService->storeForExecution(
                    execution: $execution,
                    part: $part,
                    file: $file,
                    operator: $operator,
                    note: isset($partNotes[$part->id]) ? trim((string) $partNotes[$part->id]) : null,
                );
            }
        }
    }

    /**
     * @throws ValidationException
     */
    public function findScheduleDateForExecutor(Machine $machine): PmScheduleDate
    {
        $inProgress = PmScheduleDate::query()
            ->where('machine_id', $machine->id)
            ->where('status', 'in_progress')
            ->with(['schedule.checksheetMachine.checksheet'])
            ->orderBy('scheduled_date')
            ->first();

        if ($inProgress) {
            return $inProgress;
        }

        $active = PmScheduleDate::query()
            ->where('machine_id', $machine->id)
            ->whereIn('status', ['scheduled', 'overdue'])
            ->with(['schedule.checksheetMachine.checksheet'])
            ->orderBy('scheduled_date')
            ->first();

        if (! $active) {
            throw ValidationException::withMessages([
                'machine' => 'Tidak ada jadwal PM aktif untuk mesin ini.',
            ]);
        }

        return $active;
    }

    protected function findInProgressExecution(Machine $machine, PmScheduleDate $scheduleDate): ?PmExecution
    {
        return PmExecution::query()
            ->where('machine_id', $machine->id)
            ->where('pm_schedule_date_id', $scheduleDate->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();
    }

    protected function ensureNextScheduleDateExists(PmScheduleDate $scheduleDate): void
    {
        $scheduleDate->loadMissing('schedule');

        if (! $scheduleDate->schedule) {
            return;
        }

        $nextExistingScheduleDate = PmScheduleDate::query()
            ->where('pm_schedule_id', $scheduleDate->pm_schedule_id)
            ->where('machine_id', $scheduleDate->machine_id)
            ->whereDate('scheduled_date', '>', $scheduleDate->scheduled_date?->toDateString())
            ->orderBy('scheduled_date')
            ->first();

        if ($nextExistingScheduleDate) {
            return;
        }

        $nextScheduledDate = $this->resolveNextScheduledDate(
            $scheduleDate->schedule,
            Carbon::parse((string) $scheduleDate->scheduled_date)->startOfDay(),
        );

        if (! $nextScheduledDate) {
            return;
        }

        PmScheduleDate::query()->firstOrCreate(
            [
                'pm_schedule_id' => $scheduleDate->pm_schedule_id,
                'machine_id' => $scheduleDate->machine_id,
                'scheduled_date' => $nextScheduledDate->toDateString(),
            ],
            [
                'status' => 'scheduled',
                'status_changed_at' => null,
                'generated_at' => now(),
            ],
        );
    }

    protected function resolveNextScheduledDate(PmSchedule $schedule, Carbon $afterDate): ?Carbon
    {
        $generateUntil = $schedule->generate_until?->copy()->startOfDay();

        if (! $generateUntil) {
            return null;
        }

        $cursor = $afterDate->copy()->addDay()->startOfDay();

        while ($cursor->lte($generateUntil)) {
            $shouldInclude = false;

            if ($schedule->frequency_type === 'daily') {
                $shouldInclude = true;
            }

            if ($schedule->frequency_type === 'weekly') {
                $selectedDays = array_map('intval', $schedule->weekly_days ?? []);
                $shouldInclude = in_array((int) $cursor->dayOfWeek, $selectedDays, true);
            }

            if ($schedule->frequency_type === 'monthly') {
                $targetDay = max(1, min(31, (int) ($schedule->monthly_day ?? 1)));
                $validDay = min($targetDay, $cursor->copy()->endOfMonth()->day);
                $shouldInclude = $cursor->day === $validDay;
            }

            if ($shouldInclude) {
                return $cursor;
            }

            $cursor->addDay();
        }

        return null;
    }

    /**
     * @param  array{
     *   schedule_date: PmScheduleDate,
     *   execution: ?PmExecution,
     *   checksheet_code: string,
     *   checksheet_name: string,
     *   parts: Collection<int, PmChecksheetPart>,
     *   existing_items: array<int, PmExecutionItem>,
     *   media_by_part: array<int, Collection<int, PmExecutionMedia>>
     * }|null  $resolvedContext
     */
    public function startExecutionOnly(
        Request $request,
        Machine $machine,
        User $operator,
        ?array $resolvedContext = null,
    ): PmExecution {
        return DB::transaction(function () use ($request, $machine, $operator, $resolvedContext): PmExecution {
            $context = $resolvedContext ?? $this->getExecutorContext($machine);
            $scheduleDate = $context['schedule_date'];
            $execution = $context['execution'];

            if ($execution) {
                return $execution;
            }

            $execution = PmExecution::query()->create([
                'pm_schedule_date_id' => $scheduleDate->id,
                'machine_id' => $machine->id,
                'operator_id' => $operator->id,
                'operator_name_snapshot' => $operator->name,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            if ($scheduleDate->status !== 'in_progress') {
                $scheduleDate->forceFill([
                    'status' => 'in_progress',
                    'status_changed_at' => now(),
                ])->save();
            }

            $this->activityLogService->log(
                request: $request,
                moduleName: 'pm_executor',
                action: 'start_pm',
                description: sprintf('Mulai PM untuk mesin %s', $machine->machine_code),
                tableName: 'pm_executions',
                recordId: $execution->id,
            );

            return $execution;
        });
    }
}
