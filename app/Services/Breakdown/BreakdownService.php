<?php

namespace App\Services\Breakdown;

use App\Models\Breakdown;
use App\Models\Machine;
use App\Models\PmChecksheetPart;
use App\Models\User;
use App\Services\Auth\ActivityLogService;
use App\Services\Notification\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BreakdownService
{
    public function __construct(
        protected BreakdownMediaService $mediaService,
        protected BreakdownHistoryService $historyService,
        protected ActivityLogService $activityLogService,
        protected AdminNotificationService $notificationService,
    ) {}

    /**
     * @return array{parts:Collection<int, PmChecksheetPart>}
     */
    public function inputContext(Machine $machine): array
    {
        $parts = PmChecksheetPart::query()
            ->select('pm_checksheet_parts.id', 'pm_checksheet_parts.part_name')
            ->join('pm_checksheet_machines', 'pm_checksheet_machines.id', '=', 'pm_checksheet_parts.pm_checksheet_machine_id')
            ->where('pm_checksheet_machines.machine_id', $machine->id)
            ->where('pm_checksheet_parts.is_active', true)
            ->orderBy('pm_checksheet_parts.part_name')
            ->get();

        return [
            'parts' => $parts,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $temporaryMedia
     * @param  array<int, UploadedFile>  $uploadedMedia
     */
    public function store(Request $request, Machine $machine, User $actor, array $payload, array $temporaryMedia = [], array $uploadedMedia = []): Breakdown
    {
        if (! $machine->is_active) {
            throw ValidationException::withMessages([
                'machine' => 'Mesin nonaktif tidak dapat input breakdown baru.',
            ]);
        }

        return DB::transaction(function () use ($request, $machine, $actor, $payload, $temporaryMedia, $uploadedMedia): Breakdown {
            $partSelection = (string) $payload['part_selection'];
            $partId = null;
            $customPartName = null;
            $partNameSnapshot = null;

            if ($partSelection === 'other') {
                $customPartName = trim((string) ($payload['custom_part_name'] ?? ''));
                $partNameSnapshot = $customPartName;
            } else {
                $part = PmChecksheetPart::query()
                    ->select('pm_checksheet_parts.id', 'pm_checksheet_parts.part_name')
                    ->join('pm_checksheet_machines', 'pm_checksheet_machines.id', '=', 'pm_checksheet_parts.pm_checksheet_machine_id')
                    ->where('pm_checksheet_machines.machine_id', $machine->id)
                    ->where('pm_checksheet_parts.id', (int) $partSelection)
                    ->where('pm_checksheet_parts.is_active', true)
                    ->first();

                if (! $part) {
                    throw ValidationException::withMessages([
                        'part_selection' => 'Part mesin tidak valid untuk mesin ini.',
                    ]);
                }

                $partId = $part->id;
                $partNameSnapshot = $part->part_name;
            }

            $breakdown = Breakdown::query()->create([
                'breakdown_code' => $this->generateBreakdownCode((string) $payload['breakdown_at']),
                'machine_id' => $machine->id,
                'pm_checksheet_part_id' => $partId,
                'machine_name_snapshot' => $machine->machine_name,
                'location_name_snapshot' => (string) ($machine->location?->location_name ?? '-'),
                'part_name_snapshot' => $partNameSnapshot,
                'custom_part_name' => $customPartName,
                'problem' => (string) $payload['problem'],
                'open_note' => $payload['open_note'] !== '' ? (string) $payload['open_note'] : null,
                'status' => 'open',
                'breakdown_at' => (string) $payload['breakdown_at'],
                'created_by' => $actor->id,
                'created_by_name_snapshot' => $actor->name,
            ]);

            $temporaryMediaCollection = collect($temporaryMedia);
            if ($temporaryMediaCollection->isNotEmpty()) {
                $this->mediaService->persistTemporaryToBreakdown(
                    breakdownId: $breakdown->id,
                    temporaryMedia: $temporaryMediaCollection,
                    uploader: $actor,
                    note: $payload['open_note'] !== '' ? (string) $payload['open_note'] : null,
                );
            }

            foreach ($uploadedMedia as $file) {
                $this->mediaService->persistUploadedFileToBreakdown(
                    breakdownId: $breakdown->id,
                    file: $file,
                    uploader: $actor,
                    note: $payload['open_note'] !== '' ? (string) $payload['open_note'] : null,
                );
            }

            $this->notificationService->createUnique(
                notificationType: 'breakdown_open',
                title: sprintf(
                    'Laporan Breakdown: Mesin %s Breakdown Open',
                    $machine->machine_name,
                ),
                message: sprintf(
                    'Hai, breakdown baru tercatat untuk mesin %s. Mohon bantuan teknisi untuk segera melakukan pengecekan dan perbaikan',
                    $machine->machine_name,
                ),
                relatedTable: 'breakdowns',
                relatedId: $breakdown->id,
                targetUrl: '/breakdown/review/'.$breakdown->id,
                data: [
                    'machine_code' => $machine->machine_code,
                    'breakdown_code' => $breakdown->breakdown_code,
                ],
            );

            $this->activityLogService->log(
                request: $request,
                moduleName: 'breakdown_input',
                action: 'create_breakdown_open',
                description: sprintf('Input breakdown OPEN %s', $breakdown->breakdown_code),
                tableName: 'breakdowns',
                recordId: $breakdown->id,
            );

            return $breakdown;
        });
    }

    protected function generateBreakdownCode(string $breakdownAt): string
    {
        $date = Carbon::parse($breakdownAt)->format('Ymd');
        $prefix = 'BRK-'.$date;
        $sequence = Breakdown::query()
            ->where('breakdown_code', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function close(Request $request, Breakdown $breakdown, User $actor, array $payload): void
    {
        if ($breakdown->status !== 'open') {
            throw ValidationException::withMessages([
                'breakdown' => 'Breakdown sudah ditutup dan tidak dapat diproses ulang.',
            ]);
        }

        if ($breakdown->machine && ! $breakdown->machine->is_active) {
            throw ValidationException::withMessages([
                'breakdown' => 'Mesin nonaktif. Close breakdown tidak dapat diproses.',
            ]);
        }

        DB::transaction(function () use ($request, $breakdown, $actor, $payload): void {
            $closedAt = Carbon::parse((string) $payload['closed_at']);
            $breakdownAt = Carbon::parse((string) $breakdown->breakdown_at);
            $downtimeMinutes = max(0, $breakdownAt->diffInMinutes($closedAt));

            $oldValues = [
                'status' => (string) $breakdown->status,
                'root_cause' => $breakdown->root_cause,
                'action_taken' => $breakdown->action_taken,
                'countermeasure' => $breakdown->countermeasure,
                'closed_at' => optional($breakdown->closed_at)?->toDateTimeString(),
                'downtime_minutes' => $breakdown->downtime_minutes,
            ];

            $breakdown->forceFill([
                'root_cause' => (string) $payload['root_cause'],
                'action_taken' => (string) $payload['action_taken'],
                'countermeasure' => (string) $payload['countermeasure'],
                'closed_at' => $closedAt,
                'closed_by' => $actor->id,
                'closed_by_name_snapshot' => $actor->name,
                'downtime_minutes' => $downtimeMinutes,
                'status' => 'closed',
            ])->save();

            $changes = [
                [
                    'field_name' => 'Status Breakdown',
                    'old_value' => 'open',
                    'new_value' => 'closed',
                    'change_note' => 'Breakdown ditutup.',
                ],
                [
                    'field_name' => 'Root Cause',
                    'old_value' => $oldValues['root_cause'],
                    'new_value' => (string) $payload['root_cause'],
                    'change_note' => null,
                ],
                [
                    'field_name' => 'Action Taken',
                    'old_value' => $oldValues['action_taken'],
                    'new_value' => (string) $payload['action_taken'],
                    'change_note' => null,
                ],
                [
                    'field_name' => 'Countermeasure',
                    'old_value' => $oldValues['countermeasure'],
                    'new_value' => (string) $payload['countermeasure'],
                    'change_note' => null,
                ],
                [
                    'field_name' => 'Closed At',
                    'old_value' => $oldValues['closed_at'],
                    'new_value' => $closedAt->toDateTimeString(),
                    'change_note' => null,
                ],
                [
                    'field_name' => 'Downtime Minutes',
                    'old_value' => $oldValues['downtime_minutes'] !== null ? (string) $oldValues['downtime_minutes'] : null,
                    'new_value' => (string) $downtimeMinutes,
                    'change_note' => null,
                ],
            ];

            $this->historyService->recordMany($breakdown, $actor, $changes);

            $this->notificationService->createUnique(
                notificationType: 'breakdown_closed',
                title: sprintf(
                    'Laporan Breakdown: Mesin %s Breakdown Closed',
                    (string) ($breakdown->machine?->machine_name ?? $breakdown->machine_name_snapshot ?? '-'),
                ),
                message: sprintf(
                    'Hai, mesin %s telah selesai diperbaiki. Terima kasih kepada seluruh tim yang bertugas atas respons cepat dan kerja kerasnya!',
                    (string) ($breakdown->machine?->machine_name ?? $breakdown->machine_name_snapshot ?? '-'),
                ),
                relatedTable: 'breakdowns',
                relatedId: $breakdown->id,
                targetUrl: '/breakdown/review/'.$breakdown->id,
                data: [
                    'machine_code' => (string) ($breakdown->machine?->machine_code ?? ''),
                    'breakdown_code' => $breakdown->breakdown_code,
                    'downtime_minutes' => $downtimeMinutes,
                ],
            );

            $this->activityLogService->log(
                request: $request,
                moduleName: 'breakdown_close',
                action: 'close_breakdown',
                description: sprintf('Close breakdown %s', $breakdown->breakdown_code),
                tableName: 'breakdowns',
                recordId: $breakdown->id,
                oldValues: $oldValues,
                newValues: [
                    'status' => 'closed',
                    'root_cause' => (string) $payload['root_cause'],
                    'action_taken' => (string) $payload['action_taken'],
                    'countermeasure' => (string) $payload['countermeasure'],
                    'closed_at' => $closedAt->toDateTimeString(),
                    'closed_by' => $actor->id,
                    'closed_by_name_snapshot' => $actor->name,
                    'downtime_minutes' => $downtimeMinutes,
                ],
            );
        });
    }
}
