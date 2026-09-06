<?php

namespace App\Services\PM;

use App\Models\Location;
use App\Models\Machine;
use App\Models\PmExecution;
use App\Models\PmExecutionItem;
use App\Models\PmExecutionMedia;
use App\Models\PmScheduleDate;
use App\Models\PrimeNotification;
use App\Models\User;
use App\Services\Auth\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PmReviewService
{
    public function __construct(
        protected PmWarningService $warningService,
        protected PmExecutionHistoryService $historyService,
        protected ActivityLogService $activityLogService,
    ) {}

    /**
     * @return array{executions:LengthAwarePaginator,locations:Collection,machines:Collection,parts:Collection,pics:Collection}
     */
    public function list(array $filters): array
    {
        $executions = PmExecution::query()
            ->leftJoin('pm_schedule_dates as psd', 'psd.id', '=', 'pm_executions.pm_schedule_date_id')
            ->select('pm_executions.*')
            ->with(['machine.location', 'scheduleDate'])
            ->withCount(['items as warning_count' => fn ($query) => $query->where('is_warning', true)])
            ->whereIn('pm_executions.status', ['waiting_review', 'approved'])
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->whereHas('machine', function ($machineQuery) use ($search): void {
                            $machineQuery
                                ->where('machine_code', 'like', "%{$search}%")
                                ->orWhere('machine_name', 'like', "%{$search}%");
                        })
                        ->orWhere('operator_name_snapshot', 'like', "%{$search}%");
                });
            })
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->where('status', (string) $filters['status']))
            ->when((int) ($filters['location_id'] ?? 0) > 0, function ($query) use ($filters): void {
                $query->whereHas('machine', fn ($machineQuery) => $machineQuery->where('location_id', (int) $filters['location_id']));
            })
            ->when(($filters['start_date'] ?? '') !== '', function ($query) use ($filters): void {
                $query->whereDate('psd.scheduled_date', '>=', (string) $filters['start_date']);
            })
            ->when(($filters['end_date'] ?? '') !== '', function ($query) use ($filters): void {
                $query->whereDate('psd.scheduled_date', '<=', (string) $filters['end_date']);
            })
            ->orderByRaw("CASE WHEN pm_executions.status = 'waiting_review' THEN 0 ELSE 1 END")
            ->orderByDesc('pm_executions.submitted_at')
            ->orderByDesc('psd.scheduled_date')
            ->orderByDesc('pm_executions.id')
            ->paginate(10)
            ->withQueryString();

        return [
            'executions' => $executions,
            'locations' => Location::query()->active()->orderBy('location_name')->get(['id', 'location_name']),
            'machines' => Machine::query()->active()->orderBy('machine_code')->get(['id', 'machine_code', 'machine_name']),
            'parts' => PmExecutionItem::query()->select('part_name_snapshot')->distinct()->orderBy('part_name_snapshot')->pluck('part_name_snapshot'),
            'pics' => PmExecution::query()->select('operator_name_snapshot')->whereNotNull('operator_name_snapshot')->distinct()->orderBy('operator_name_snapshot')->pluck('operator_name_snapshot'),
        ];
    }

    public function findOrFail(int $executionId): PmExecution
    {
        return PmExecution::query()
            ->with([
                'machine.location',
                'scheduleDate',
                'items' => fn ($query) => $query->orderBy('pm_checksheet_part_id')->orderBy('id'),
                'media' => fn ($query) => $query->orderByDesc('id'),
                'history' => fn ($query) => $query->with('changer')->orderByDesc('changed_at')->orderByDesc('id'),
            ])
            ->findOrFail($executionId);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function update(Request $request, PmExecution $execution, ?User $admin, array $payload): void
    {
        if ($execution->status === 'approved') {
            throw ValidationException::withMessages([
                'execution' => 'Hasil PM yang sudah approved tidak dapat diedit lagi.',
            ]);
        }

        DB::transaction(function () use ($request, $execution, $admin, $payload): void {
            $changes = [];
            $itemsById = $execution->items()->get()->keyBy('id');

            $newSubmittedAt = Carbon::parse((string) $payload['submitted_at']);
            $oldSubmittedAt = $execution->submitted_at?->copy();

            if (! $oldSubmittedAt || ! $oldSubmittedAt->equalTo($newSubmittedAt)) {
                $changes[] = [
                    'field_name' => 'Tanggal Submit PM',
                    'old_value' => $oldSubmittedAt?->toDateTimeString(),
                    'new_value' => $newSubmittedAt->toDateTimeString(),
                    'change_note' => (string) $payload['change_note'],
                ];

                $execution->submitted_at = $newSubmittedAt;
                $execution->save();
            }

            foreach ((array) ($payload['items'] ?? []) as $itemId => $editedItem) {
                $item = $itemsById->get((int) $itemId);

                if (! $item) {
                    continue;
                }

                $isAction = $item->input_type_snapshot === 'action';
                $oldValue = $this->itemDisplayValue($item);

                if ($isAction) {
                    $newAction = strtoupper(trim((string) ($editedItem['action_value'] ?? '')));
                    $options = collect($item->action_options_snapshot ?? [])->map(fn ($option): string => strtoupper((string) $option))->all();

                    if ($newAction === '' || ! in_array($newAction, $options, true)) {
                        throw ValidationException::withMessages([
                            "items.{$item->id}.action_value" => 'Nilai action harus dipilih dari opsi yang tersedia.',
                        ]);
                    }

                    $item->action_value = $newAction;
                    $item->number_value = null;
                } else {
                    $rawNumber = $editedItem['number_value'] ?? null;
                    $numberValue = ($rawNumber === null || $rawNumber === '') ? null : (float) $rawNumber;

                    if ($numberValue === null) {
                        throw ValidationException::withMessages([
                            "items.{$item->id}.number_value" => 'Nilai number/range wajib diisi.',
                        ]);
                    }

                    $item->number_value = $numberValue;
                    $item->action_value = null;

                    $warning = $this->warningService->evaluate([
                        'input_type' => (string) $item->input_type_snapshot,
                        'target_value' => $item->target_value_snapshot,
                        'min_value' => $item->min_value_snapshot,
                        'max_value' => $item->max_value_snapshot,
                        'unit' => $item->unit_snapshot,
                    ], $numberValue);

                    $item->is_warning = $warning['is_warning'];
                    $item->warning_message = $warning['warning_message'];
                }

                $newValue = $this->itemDisplayValue($item);
                if ($oldValue !== $newValue) {
                    $changes[] = [
                        'field_name' => $item->standard_name_snapshot,
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                        'change_note' => (string) $payload['change_note'],
                    ];
                }

                $item->save();
            }

            foreach ((array) ($payload['part_notes'] ?? []) as $partId => $note) {
                $partItems = $execution->items()->where('pm_checksheet_part_id', (int) $partId)->get();

                if ($partItems->isEmpty()) {
                    continue;
                }

                $newNote = trim((string) $note);
                $oldNote = (string) ($partItems->first()->note ?? '');

                if ($oldNote !== $newNote) {
                    $changes[] = [
                        'field_name' => 'Catatan Part: '.(string) $partItems->first()->part_name_snapshot,
                        'old_value' => $oldNote,
                        'new_value' => $newNote,
                        'change_note' => (string) $payload['change_note'],
                    ];

                    $execution->items()->where('pm_checksheet_part_id', (int) $partId)->update([
                        'note' => $newNote,
                        'updated_at' => now(),
                    ]);
                }
            }

            if (($payload['review_note'] ?? null) !== null) {
                $reviewNote = trim((string) $payload['review_note']);
                if ((string) ($execution->review_note ?? '') !== $reviewNote) {
                    $changes[] = [
                        'field_name' => 'Review Note',
                        'old_value' => (string) ($execution->review_note ?? ''),
                        'new_value' => $reviewNote,
                        'change_note' => (string) $payload['change_note'],
                    ];

                    $execution->review_note = $reviewNote;
                    $execution->save();
                }
            }

            $this->historyService->recordMany($execution, $admin, $changes);

            $this->activityLogService->log(
                request: $request,
                moduleName: 'pm_review',
                action: 'update',
                description: sprintf('Update PM execution #%d', $execution->id),
                tableName: 'pm_executions',
                recordId: $execution->id,
            );
        });
    }

    public function approve(Request $request, PmExecution $execution, ?User $admin, ?string $reviewNote): void
    {
        // Precheck cepat untuk UX; keputusan final tetap memakai re-read terkunci
        // dalam transaction agar stale model tidak dapat melewati lifecycle check.
        if ($execution->status === 'approved') {
            throw ValidationException::withMessages([
                'execution' => 'Hasil PM ini sudah berstatus approved.',
            ]);
        }

        DB::transaction(function () use ($request, $execution, $admin, $reviewNote): void {
            // ADR-004: partial order approval harus mengikuti Machine → PmScheduleDate → PmExecution.
            $machine = Machine::query()
                ->whereKey($execution->machine_id)
                ->lockForUpdate()
                ->first();

            if (! $machine) {
                throw ValidationException::withMessages([
                    'execution' => 'Mesin parent execution tidak ditemukan; approval dihentikan.',
                ]);
            }

            $scheduleDate = PmScheduleDate::query()
                ->whereKey($execution->pm_schedule_date_id)
                ->where('machine_id', $machine->id)
                ->lockForUpdate()
                ->first();

            if (! $scheduleDate) {
                throw ValidationException::withMessages([
                    'execution' => 'Occurrence parent execution tidak ditemukan atau tidak sesuai mesin; approval dihentikan.',
                ]);
            }

            // Parent harus valid sebelum execution dikunci; tidak ada fallback
            // execution-only yang dapat memutasi histori tanpa occurrence.
            $lockedExecution = PmExecution::query()
                ->whereKey($execution->id)
                ->where('pm_schedule_date_id', $scheduleDate->id)
                ->where('machine_id', $machine->id)
                ->lockForUpdate()
                ->first();
            if (! $lockedExecution) {
                $mismatchedExecution = PmExecution::query()
                    ->whereKey($execution->id)
                    ->where('pm_schedule_date_id', $scheduleDate->id)
                    ->lockForUpdate()
                    ->first();

                if ($mismatchedExecution) {
                    throw ValidationException::withMessages([
                        'execution' => 'Execution PM memiliki relasi mesin yang tidak sesuai dengan occurrence; approval dihentikan.',
                    ]);
                }

                throw ValidationException::withMessages([
                    'execution' => 'Transaksi PM tidak ditemukan.',
                ]);
            }

            // Re-read lifecycle dari database sebelum mutation; status apa pun selain
            // waiting_review ditolak untuk mencegah double approval dan bypass lifecycle.
            if ($lockedExecution->status !== 'waiting_review') {
                throw ValidationException::withMessages([
                    'execution' => sprintf('Hasil PM ini sudah berstatus %s.', $lockedExecution->status),
                ]);
            }

            $execution = $lockedExecution;

            $execution->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $admin?->id,
                'approved_by_name_snapshot' => $admin?->name,
                'review_note' => $reviewNote !== null ? trim($reviewNote) : $execution->review_note,
            ])->save();

            // Occurrence hanya dimutasi jika relasi parent masih valid; row locked sebelumnya
            // mencegah schedule date berubah di tengah approval transaction.
            $scheduleDate?->forceFill([
                'status' => 'approved',
                'status_changed_at' => now(),
            ])->save();

            if ($admin) {
                $this->historyService->record(
                    execution: $execution,
                    admin: $admin,
                    fieldName: 'Status PM',
                    oldValue: 'waiting_review',
                    newValue: 'approved',
                    changeNote: trim((string) ($reviewNote ?: 'Approved by admin')),
                );
            }

            PrimeNotification::query()
                ->where('notification_type', 'pm_waiting_review')
                ->where('related_table', 'pm_executions')
                ->where('related_id', $execution->id)
                ->delete();

            $this->activityLogService->log(
                request: $request,
                moduleName: 'pm_review',
                action: 'approve',
                description: sprintf('Approve PM execution #%d', $execution->id),
                tableName: 'pm_executions',
                recordId: $execution->id,
            );
        });
    }

    /**
     * Hapus PM execution melalui normal PM Review DELETE workflow.
     *
     * Mengembalikan array result dengan kunci 'allowed' dan 'message'.
     * Jika execution sudah memiliki status protected (in_progress, waiting_review, approved),
     * operasi ditolak tanpa mutation apapun untuk melindungi historical transaction.
     *
     * @return array{allowed:bool,message:string}
     */
    public function delete(Request $request, PmExecution $execution): array
    {
        return DB::transaction(function () use ($request, $execution): array {
            // Re-read execution with lock untuk mencegah race condition
            // antara pengecekan status dan mutation
            $lockedExecution = PmExecution::query()
                ->where('id', $execution->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedExecution) {
                return [
                    'allowed' => false,
                    'message' => 'Transaksi PM tidak ditemukan.',
                ];
            }

            // Protected status check DI DALAM transaksi dengan lock
            $protectedStatuses = ['in_progress', 'waiting_review', 'approved'];
            if (in_array($lockedExecution->status, $protectedStatuses, true)) {
                return [
                    'allowed' => false,
                    'message' => 'Transaksi PM yang sudah dimulai tidak dapat dihapus karena merupakan data pekerjaan/histori.',
                ];
            }

            $scheduleDate = $lockedExecution->scheduleDate;

            $this->activityLogService->log(
                request: $request,
                moduleName: 'pm_review',
                action: 'delete',
                description: sprintf('Delete PM execution #%d', $lockedExecution->id),
                tableName: 'pm_executions',
                recordId: $lockedExecution->id,
                oldValues: [
                    'status' => $lockedExecution->status,
                    'machine_id' => $lockedExecution->machine_id,
                    'operator_name_snapshot' => $lockedExecution->operator_name_snapshot,
                ],
            );

            $mediaList = PmExecutionMedia::query()->where('pm_execution_id', $lockedExecution->id)->get();
            foreach ($mediaList as $media) {
                if ($media->file_path) {
                    Storage::disk('public')->delete($media->file_path);
                }
                if ($media->original_file_path) {
                    Storage::disk('public')->delete($media->original_file_path);
                }
            }

            $lockedExecution->forceDelete();

            if ($scheduleDate) {
                $scheduleDate->forceFill([
                    'status' => 'scheduled',
                    'status_changed_at' => now(),
                ])->save();
            }

            PrimeNotification::query()
                ->where('related_table', 'pm_executions')
                ->where('related_id', $lockedExecution->id)
                ->delete();

            return [
                'allowed' => true,
                'message' => 'Hasil PM berhasil dihapus permanen.',
            ];
        });
    }

    protected function itemDisplayValue(PmExecutionItem $item): string
    {
        if ($item->input_type_snapshot === 'action') {
            return (string) ($item->action_value ?? '');
        }

        return $item->number_value !== null
            ? rtrim(rtrim((string) number_format((float) $item->number_value, 2, '.', ''), '0'), '.')
            : '';
    }
}
