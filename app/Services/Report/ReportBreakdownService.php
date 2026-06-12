<?php

namespace App\Services\Report;

use App\Models\Breakdown;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportBreakdownService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   rows:LengthAwarePaginator,
     *   locations:Collection<int, Location>
     * }
     */
    public function list(array $filters): array
    {
        return [
            'rows' => $this->baseQuery($filters)->paginate(10)->withQueryString(),
            'locations' => Location::query()->active()->orderBy('location_name')->get(['id', 'location_name']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Breakdown>
     */
    public function exportRows(array $filters): Collection
    {
        return $this->baseQuery($filters)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(array $filters): Builder
    {
        return Breakdown::query()
            ->with('machine.location')
            ->when(
                $filters['start_date'] ?? null,
                fn (Builder $query, string $startDate) => $query->where('breakdown_at', '>=', $startDate.' 00:00:00')
            )
            ->when(
                $filters['end_date'] ?? null,
                fn (Builder $query, string $endDate) => $query->where('breakdown_at', '<=', $endDate.' 23:59:59')
            )
            ->when($filters['location_id'] ?? null, function (Builder $query, int $locationId): void {
                $query->whereHas('machine', fn (Builder $machineQuery) => $machineQuery->where('location_id', $locationId));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', strtolower($status)))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('breakdown_code', 'like', "%{$search}%")
                        ->orWhere('problem', 'like', "%{$search}%")
                        ->orWhere('part_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('custom_part_name', 'like', "%{$search}%")
                        ->orWhere('root_cause', 'like', "%{$search}%")
                        ->orWhere('action_taken', 'like', "%{$search}%")
                        ->orWhere('countermeasure', 'like', "%{$search}%")
                        ->orWhere('created_by_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('closed_by_name_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('machine', function (Builder $machineQuery) use ($search): void {
                            $machineQuery
                                ->where('machine_code', 'like', "%{$search}%")
                                ->orWhere('machine_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('breakdown_at')
            ->orderByDesc('downtime_minutes')
            ->orderByDesc('id');
    }

    public function statusLabel(Breakdown $breakdown): string
    {
        return strtoupper((string) $breakdown->status);
    }

    public function downtimeHourLabel(Breakdown $breakdown): string
    {
        $minutes = $breakdown->status === 'closed'
            ? (int) ($breakdown->downtime_minutes ?? 0)
            : max(0, Carbon::parse((string) $breakdown->breakdown_at)->diffInMinutes(now()));

        if ($minutes <= 0) {
            return '-';
        }

        $hours = $minutes / 60;

        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.').' jam';
    }

    public function dateTimeOrDash(?Carbon $dateTime, string $format = 'd M Y, H:i'): string
    {
        return $dateTime?->format($format) ?? '-';
    }
}
