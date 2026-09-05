<?php

namespace App\Services\Breakdown;

use App\Models\Breakdown;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PrimeNotification;
use App\Models\User;
use App\Services\Auth\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BreakdownReviewService
{
    public function __construct(
        protected BreakdownHistoryService $historyService,
        protected ActivityLogService $activityLogService,
    ) {
    }

    /**
     * @return array{
     *     breakdowns: LengthAwarePaginator,
     *     locations: Collection<int, Location>,
     *     machines: Collection<int, Machine>,
     *     parts: Collection<int, string>,
     *     pics: Collection<int, string>,
     *     closePics: Collection<int, string>
     * }
     */
    public function list(array $filters): array
    {
        $query = Breakdown::query()
            ->with('machine.location')
            ->when(($filters['search'] ?? '') !== '', function ($builder) use ($filters): void {
                $search = trim((string) $filters['search']);
                $builder->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('breakdown_code', 'like', "%{$search}%")
                        ->orWhere('problem', 'like', "%{$search}%")
                        ->orWhere('machine_name_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('machine', function ($machineQuery) use ($search): void {
                            $machineQuery
                                ->where('machine_code', 'like', "%{$search}%")
                                ->orWhere('machine_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(($filters['status'] ?? '') !== '', fn ($builder) => $builder->where('status', (string) $filters['status']))
            ->when((int) ($filters['location_id'] ?? 0) > 0, function ($builder) use ($filters): void {
                $builder->whereHas('machine', fn ($machineQuery) => $machineQuery->where('location_id', (int) $filters['location_id']));
            })
            ->when((int) ($filters['machine_id'] ?? 0) > 0, fn ($builder) => $builder->where('machine_id', (int) $filters['machine_id']))
            ->when(($filters['part_name'] ?? '') !== '', function ($builder) use ($filters): void {
                $part = trim((string) $filters['part_name']);
                $builder->where(function ($sub) use ($part): void {
                    $sub->where('part_name_snapshot', $part)->orWhere('custom_part_name', $part);
                });
            })
            ->when(($filters['pic_open'] ?? '') !== '', fn ($builder) => $builder->where('created_by_name_snapshot', trim((string) $filters['pic_open'])))
            ->when(($filters['pic_close'] ?? '') !== '', fn ($builder) => $builder->where('closed_by_name_snapshot', trim((string) $filters['pic_close'])))
            ->when(($filters['date_field'] ?? '') !== '' && ($filters['date'] ?? '') !== '', function ($builder) use ($filters): void {
                $dateField = (string) $filters['date_field'] === 'closed_at' ? 'closed_at' : 'breakdown_at';
                $builder->whereDate($dateField, (string) $filters['date']);
            })
            ->when(($filters['min_downtime_hours'] ?? null) !== null && $filters['min_downtime_hours'] !== '', function ($builder) use ($filters): void {
                $minutes = (int) ((float) $filters['min_downtime_hours'] * 60);
                $builder->where(function ($sub) use ($minutes): void {
                    $sub->where('downtime_minutes', '>=', $minutes)
                        ->orWhere(function ($open) use ($minutes): void {
                            $open->where('status', 'open')
                                ->where('breakdown_at', '<=', now()->subMinutes($minutes));
                        });
                });
            })
            ->orderByDesc('breakdown_at')
            ->orderByDesc('id');

        $breakdowns = $query->paginate(10)->withQueryString();

        return [
            'breakdowns' => $breakdowns,
            'locations' => Location::query()->orderBy('location_name')->get(['id', 'location_name']),
            'machines' => Machine::query()->orderBy('machine_name')->get(['id', 'machine_code', 'machine_name']),
            'parts' => Breakdown::query()->select('part_name_snapshot')->whereNotNull('part_name_snapshot')->distinct()->orderBy('part_name_snapshot')->pluck('part_name_snapshot'),
            'pics' => Breakdown::query()->select('created_by_name_snapshot')->whereNotNull('created_by_name_snapshot')->distinct()->orderBy('created_by_name_snapshot')->pluck('created_by_name_snapshot'),
            'closePics' => Breakdown::query()->select('closed_by_name_snapshot')->whereNotNull('closed_by_name_snapshot')->distinct()->orderBy('closed_by_name_snapshot')->pluck('closed_by_name_snapshot'),
        ];
    }

    public function findOrFail(int $id): Breakdown
    {
        return Breakdown::query()
            ->with([
                'machine.location',
                'media' => fn ($query) => $query->orderByDesc('id'),
                'history' => fn ($query) => $query->with('changer')->orderByDesc('changed_at')->orderByDesc('id'),
            ])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @throws ValidationException
     */
    public function update(Request $request, Breakdown $breakdown, ?User $admin, array $payload): void
    {
        DB::transaction(function () use ($request, $breakdown, $admin, $payload): void {
            $changes = [];
            $changeNote = (string) $payload['change_note'];

            $currentBreakdownAt = Carbon::parse((string) $breakdown->breakdown_at);
            $newBreakdownAt = Carbon::parse((string) $payload['breakdown_at']);
            $newClosedAt = ! empty($payload['closed_at']) ? Carbon::parse((string) $payload['closed_at']) : null;

            if ((string) $payload['status'] === 'closed' && ! $newClosedAt) {
                throw ValidationException::withMessages([
                    'closed_at' => 'Waktu close wajib diisi untuk status CLOSED.',
                ]);
            }

            if ((string) $payload['status'] === 'closed' && $newClosedAt && $newClosedAt->lt($newBreakdownAt)) {
                throw ValidationException::withMessages([
                    'closed_at' => 'Waktu close tidak boleh lebih kecil dari breakdown at.',
                ]);
            }

            $map = [
                'problem' => (string) $payload['problem'],
                'open_note' => ($payload['open_note'] ?? '') !== '' ? (string) $payload['open_note'] : null,
                'root_cause' => ($payload['root_cause'] ?? '') !== '' ? (string) $payload['root_cause'] : null,
                'action_taken' => ($payload['action_taken'] ?? '') !== '' ? (string) $payload['action_taken'] : null,
                'countermeasure' => ($payload['countermeasure'] ?? '') !== '' ? (string) $payload['countermeasure'] : null,
                'status' => (string) $payload['status'],
            ];

            foreach ($map as $field => $newValue) {
                $oldValue = $breakdown->{$field};
                if ((string) $oldValue !== (string) $newValue) {
                    $changes[] = [
                        'field_name' => $field,
                        'old_value' => $oldValue !== null ? (string) $oldValue : null,
                        'new_value' => $newValue !== null ? (string) $newValue : null,
                        'change_note' => $changeNote,
                    ];
                }
            }

            if ($currentBreakdownAt->toDateTimeString() !== $newBreakdownAt->toDateTimeString()) {
                $changes[] = [
                    'field_name' => 'breakdown_at',
                    'old_value' => $currentBreakdownAt->toDateTimeString(),
                    'new_value' => $newBreakdownAt->toDateTimeString(),
                    'change_note' => $changeNote,
                ];
            }

            $oldClosedAt = $breakdown->closed_at ? Carbon::parse((string) $breakdown->closed_at)->toDateTimeString() : null;
            $newClosedAtString = $newClosedAt?->toDateTimeString();
            if ($oldClosedAt !== $newClosedAtString) {
                $changes[] = [
                    'field_name' => 'closed_at',
                    'old_value' => $oldClosedAt,
                    'new_value' => $newClosedAtString,
                    'change_note' => $changeNote,
                ];
            }

            $downtime = null;
            if ((string) $payload['status'] === 'closed' && $newClosedAt) {
                $downtime = max(0, $newBreakdownAt->diffInMinutes($newClosedAt));
            }

            if ($breakdown->downtime_minutes !== $downtime) {
                $changes[] = [
                    'field_name' => 'downtime_minutes',
                    'old_value' => $breakdown->downtime_minutes !== null ? (string) $breakdown->downtime_minutes : null,
                    'new_value' => $downtime !== null ? (string) $downtime : null,
                    'change_note' => $changeNote,
                ];
            }

            $breakdown->forceFill([
                'problem' => (string) $payload['problem'],
                'open_note' => ($payload['open_note'] ?? '') !== '' ? (string) $payload['open_note'] : null,
                'breakdown_at' => $newBreakdownAt,
                'root_cause' => ($payload['root_cause'] ?? '') !== '' ? (string) $payload['root_cause'] : null,
                'action_taken' => ($payload['action_taken'] ?? '') !== '' ? (string) $payload['action_taken'] : null,
                'countermeasure' => ($payload['countermeasure'] ?? '') !== '' ? (string) $payload['countermeasure'] : null,
                'status' => (string) $payload['status'],
                'closed_at' => $newClosedAt,
                'closed_by' => (string) $payload['status'] === 'closed' ? ($breakdown->closed_by ?: $admin?->id) : null,
                'closed_by_name_snapshot' => (string) $payload['status'] === 'closed' ? ($breakdown->closed_by_name_snapshot ?: $admin?->name) : null,
                'downtime_minutes' => $downtime,
            ])->save();

            if ($changes !== []) {
                $this->historyService->recordMany($breakdown, $admin, $changes);
            }

            $this->activityLogService->log(
                request: $request,
                moduleName: 'breakdown_review',
                action: 'update',
                description: sprintf('Update breakdown %s', $breakdown->breakdown_code),
                tableName: 'breakdowns',
                recordId: $breakdown->id,
            );
        });
    }

    /**
     * Hapus breakdown.
     *
     * BREAKDOWN DELETE PROTECTION (TASK-003 SLICE 2):
     * Semua breakdown yang sudah tercatat (status OPEN atau CLOSED) tidak dapat dihapus
     * karena merupakan riwayat kejadian mesin yang harus dipertahankan sesuai ADR-003.
     *
     * @return array{allowed:bool,message:string}
     */
    public function delete(Request $request, Breakdown $breakdown): array
    {
        return DB::transaction(function () use ($request, $breakdown): array {
            // Re-read dengan lock untuk mencegah race condition antara pengecekan status dan mutation
            $locked = Breakdown::query()
                ->where('id', $breakdown->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return [
                    'allowed' => false,
                    'message' => 'Data breakdown tidak ditemukan.',
                ];
            }

            // Guard: Semua breakdown dengan status OPEN atau CLOSED adalah data historis
            // yang dilindungi dan tidak dapat dihapus
            if (in_array($locked->status, ['open', 'closed'], true)) {
                return [
                    'allowed' => false,
                    'message' => 'Data breakdown yang sudah tercatat tidak dapat dihapus karena merupakan riwayat kejadian mesin.',
                ];
            }

            $this->activityLogService->log(
                request: $request,
                moduleName: 'breakdown_review',
                action: 'delete',
                description: sprintf('Delete breakdown %s', $locked->breakdown_code),
                tableName: 'breakdowns',
                recordId: $locked->id,
                oldValues: [
                    'breakdown_code' => $locked->breakdown_code,
                    'status' => $locked->status,
                    'machine_id' => $locked->machine_id,
                ],
            );

            PrimeNotification::query()
                ->where('related_table', 'breakdowns')
                ->where('related_id', $locked->id)
                ->delete();

            $locked->forceDelete();

            return [
                'allowed' => true,
                'message' => sprintf('Breakdown %s berhasil dihapus permanen.', $locked->breakdown_code),
            ];
        });
    }
}
