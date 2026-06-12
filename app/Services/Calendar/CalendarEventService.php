<?php

namespace App\Services\Calendar;

use App\Models\Breakdown;
use App\Models\Location;
use App\Models\Machine;
use App\Models\PmScheduleDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CalendarEventService
{
    /**
     * @var array<string, string>
     */
    private array $statusColorMap = [
        'scheduled' => '#3b82f6',
        'in_progress' => '#f59e0b',
        'waiting_review' => '#8b5cf6',
        'approved' => '#10b981',
        'overdue' => '#ef4444',
        'missed' => '#7f1d1d',
        'open' => '#dc2626',
        'closed' => '#16a34a',
    ];

    /**
     * @return array{locations: array<int, array{id: int, name: string}>, machines: array<int, array{id: int, name: string}>}
     */
    public function filterOptions(): array
    {
        $locations = Location::query()
            ->select(['id', 'location_name'])
            ->orderBy('location_name')
            ->get()
            ->map(fn (Location $location): array => ['id' => $location->id, 'name' => $location->location_name])
            ->all();

        $machines = Machine::query()
            ->select(['id', 'machine_code', 'machine_name'])
            ->orderBy('machine_name')
            ->get()
            ->map(fn (Machine $machine): array => ['id' => $machine->id, 'name' => "{$machine->machine_code} - {$machine->machine_name}"])
            ->all();

        return compact('locations', 'machines');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function events(string $role, array $filters): array
    {
        $range = $this->resolveRange(
            isset($filters['start']) ? (string) $filters['start'] : null,
            isset($filters['end']) ? (string) $filters['end'] : null,
        );

        $eventType = (string) ($filters['event_type'] ?? 'all');
        $status = isset($filters['status']) ? (string) $filters['status'] : null;
        $locationId = isset($filters['location_id']) ? (int) $filters['location_id'] : null;
        $machineId = isset($filters['machine_id']) ? (int) $filters['machine_id'] : null;

        $events = [];

        $includePm = $eventType === 'all' || $eventType === 'pm';
        $includeBreakdown = $eventType === 'all' || $eventType === 'breakdown';

        if ($status !== null) {
            if (in_array($status, ['scheduled', 'in_progress', 'waiting_review', 'approved', 'overdue', 'missed'], true)) {
                $includeBreakdown = false;
            }

            if (in_array($status, ['open', 'closed'], true)) {
                $includePm = false;
            }
        }

        if ($includePm) {
            $events = [...$events, ...$this->pmEvents($role, $range['start'], $range['end'], $status, $locationId, $machineId)];
        }

        if ($includeBreakdown) {
            $events = [...$events, ...$this->breakdownEvents($role, $range['start'], $range['end'], $status, $locationId, $machineId)];
        }

        return $events;
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolveRange(?string $start, ?string $end): array
    {
        $startDate = $start !== null ? Carbon::parse($start)->startOfDay() : now()->startOfMonth();
        $endDate = $end !== null ? Carbon::parse($end)->subSecond() : now()->endOfMonth();

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pmEvents(
        string $role,
        Carbon $start,
        Carbon $end,
        ?string $status,
        ?int $locationId,
        ?int $machineId,
    ): array {
        $query = PmScheduleDate::query()
            ->with([
                'machine:id,machine_code,machine_name,location_id',
                'machine.location:id,location_name',
                'executions' => fn ($executionQuery) => $executionQuery
                    ->select(['id', 'pm_schedule_date_id', 'status', 'submitted_at', 'created_at'])
                    ->orderByDesc('submitted_at')
                    ->orderByDesc('created_at'),
            ])
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()]);

        if ($machineId !== null) {
            $query->where('machine_id', $machineId);
        }

        if ($status !== null && in_array($status, ['scheduled', 'in_progress', 'waiting_review', 'approved', 'overdue', 'missed'], true)) {
            $query->where('status', $status);
        }

        if ($locationId !== null) {
            $query->whereHas('machine', fn (Builder $machineQuery) => $machineQuery->where('location_id', $locationId));
        }

        return $query
            ->get()
            ->map(function (PmScheduleDate $scheduleDate) use ($role): array {
                $machine = $scheduleDate->machine;
                $execution = $scheduleDate->executions->first();
                $calendarStatus = (string) $scheduleDate->status;

                $url = null;
                if ($role === 'admin') {
                    $url = $this->pmRedirectUrl($scheduleDate, $execution?->id);
                }

                return [
                    'id' => "pm-{$scheduleDate->id}",
                    'title' => 'PM - ' . ($machine?->machine_name ?? 'Mesin'),
                    'start' => $scheduleDate->scheduled_date?->toDateString(),
                    'allDay' => true,
                    'url' => $url,
                    'backgroundColor' => $this->statusColorMap[$calendarStatus],
                    'borderColor' => $this->statusColorMap[$calendarStatus],
                    'extendedProps' => [
                        'eventType' => 'pm',
                        'status' => $calendarStatus,
                        'machine_name' => $machine?->machine_name,
                        'machine_code' => $machine?->machine_code,
                        'location_name' => $machine?->location?->location_name,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function breakdownEvents(
        string $role,
        Carbon $start,
        Carbon $end,
        ?string $status,
        ?int $locationId,
        ?int $machineId,
    ): array {
        $query = Breakdown::query()
            ->with([
                'machine:id,machine_code,machine_name,location_id',
                'machine.location:id,location_name',
            ])
            ->where('breakdown_at', '<=', $end)
            ->where(function (Builder $builder) use ($start): void {
                $builder
                    ->whereNull('closed_at')
                    ->orWhere('closed_at', '>=', $start);
            });

        if ($machineId !== null) {
            $query->where('machine_id', $machineId);
        }

        if ($status !== null && in_array($status, ['open', 'closed'], true)) {
            $query->where('status', $status);
        }

        if ($locationId !== null) {
            $query->whereHas('machine', fn (Builder $machineQuery) => $machineQuery->where('location_id', $locationId));
        }

        return $query
            ->get()
            ->map(function (Breakdown $breakdown) use ($role): array {
                $machine = $breakdown->machine;
                $calendarStatus = (string) $breakdown->status;

                return [
                    'id' => "bd-{$breakdown->id}",
                    'title' => 'Breakdown - ' . ($machine?->machine_name ?? 'Mesin'),
                    'start' => $breakdown->breakdown_at?->toIso8601String(),
                    'end' => $breakdown->closed_at?->toIso8601String(),
                    'url' => $role === 'admin' ? route('breakdown-review.show', $breakdown->id) : null,
                    'backgroundColor' => $this->statusColorMap[$calendarStatus],
                    'borderColor' => $this->statusColorMap[$calendarStatus],
                    'extendedProps' => [
                        'eventType' => 'breakdown',
                        'status' => $calendarStatus,
                        'machine_name' => $machine?->machine_name,
                        'machine_code' => $machine?->machine_code,
                        'location_name' => $machine?->location?->location_name,
                    ],
                ];
            })
            ->all();
    }

    private function pmRedirectUrl(PmScheduleDate $scheduleDate, ?int $executionId): string
    {
        if (
            in_array($scheduleDate->status, ['waiting_review', 'approved'], true)
            && $executionId !== null
        ) {
            return route('pm-review.show', $executionId);
        }

        return route('machines.show', $scheduleDate->machine);
    }
}
