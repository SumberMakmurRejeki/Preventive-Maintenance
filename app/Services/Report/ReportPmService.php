<?php

namespace App\Services\Report;

use App\Models\Location;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportPmService
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
     * @return Collection<int, object>
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
        return DB::table('pm_schedule_dates as psd')
            ->join('machines as m', 'm.id', '=', 'psd.machine_id')
            ->leftJoin('locations as l', 'l.id', '=', 'm.location_id')
            ->leftJoin('pm_executions as pe', function ($join): void {
                $join->on('pe.pm_schedule_date_id', '=', 'psd.id')
                    ->whereNull('pe.deleted_at');
            })
            ->leftJoin('pm_execution_items as pei', 'pei.pm_execution_id', '=', 'pe.id')
            ->whereNull('psd.deleted_at')
            ->when(
                $filters['start_date'] ?? null,
                fn (Builder $query, string $startDate) => $query->where('psd.scheduled_date', '>=', $startDate.' 00:00:00')
            )
            ->when(
                $filters['end_date'] ?? null,
                fn (Builder $query, string $endDate) => $query->where('psd.scheduled_date', '<=', $endDate.' 23:59:59')
            )
            ->when($filters['location_id'] ?? null, fn (Builder $query, int $locationId) => $query->where('m.location_id', $locationId))
            ->when($filters['machine_id'] ?? null, fn (Builder $query, int $machineId) => $query->where('psd.machine_id', $machineId))
            ->when($filters['part_name'] ?? null, fn (Builder $query, string $partName) => $query->where('pei.part_name_snapshot', $partName))
            ->when($filters['pic_operator'] ?? null, fn (Builder $query, string $picOperator) => $query->where('pe.operator_name_snapshot', $picOperator))
            ->when($filters['pic_admin'] ?? null, fn (Builder $query, string $picAdmin) => $query->where('pe.approved_by_name_snapshot', $picAdmin))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->whereRaw('LOWER(COALESCE(pe.status, psd.status)) = ?', [strtolower($status)]))
            ->when($filters['warning'] ?? null, function (Builder $query, string $warning): void {
                if ($warning === 'warning') {
                    $query->where('pei.is_warning', true);
                }

                if ($warning === 'normal') {
                    $query->where(function (Builder $warningQuery): void {
                        $warningQuery->whereNull('pei.is_warning')->orWhere('pei.is_warning', false);
                    });
                }
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('m.machine_code', 'like', "%{$search}%")
                        ->orWhere('m.machine_name', 'like', "%{$search}%")
                        ->orWhere('pei.part_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('pei.standard_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('pe.operator_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('pe.approved_by_name_snapshot', 'like', "%{$search}%");
                });
            })
            ->select([
                'psd.id as schedule_date_id',
                'psd.scheduled_date',
                'psd.status as schedule_status',
                'm.machine_code',
                'm.machine_name',
                'l.location_name',
                'pe.id as execution_id',
                'pe.status as execution_status',
                'pe.operator_name_snapshot',
                'pe.approved_by_name_snapshot',
                'pe.submitted_at',
                'pe.approved_at',
                'pei.id as execution_item_id',
                'pei.part_name_snapshot',
                'pei.standard_name_snapshot',
                'pei.input_type_snapshot',
                'pei.action_value',
                'pei.number_value',
                'pei.min_value_snapshot',
                'pei.max_value_snapshot',
                'pei.unit_snapshot',
                'pei.is_warning',
                'pei.note as operator_note',
            ])
            ->orderByRaw("CASE WHEN LOWER(COALESCE(pe.status, psd.status)) IN ('overdue', 'missed') THEN 0 ELSE 1 END")
            ->orderByDesc('psd.scheduled_date')
            ->orderBy('m.machine_code')
            ->orderByDesc('psd.id')
            ->orderBy('pei.id');
    }

    public function statusLabel(object $row): string
    {
        $status = strtolower((string) ($row->execution_status ?: $row->schedule_status));

        return match ($status) {
            'scheduled' => 'Scheduled',
            'in_progress' => 'In Progress',
            'waiting_review' => 'Waiting Review',
            'approved' => 'Approved',
            'overdue' => 'Overdue',
            'missed' => 'Missed',
            default => '-',
        };
    }

    public function resultAction(object $row): string
    {
        if ((string) ($row->input_type_snapshot ?? '') !== 'action') {
            return '-';
        }

        return (string) ($row->action_value ?: '-');
    }

    public function resultValue(object $row): string
    {
        $inputType = (string) ($row->input_type_snapshot ?? '');

        if ($inputType === 'number') {
            if ($row->number_value === null) {
                return '-';
            }

            return rtrim(rtrim(number_format((float) $row->number_value, 2, '.', ''), '0'), '.');
        }

        if ($inputType === 'range') {
            $minimum = $row->min_value_snapshot;
            $maximum = $row->max_value_snapshot;

            if ($minimum === null || $maximum === null) {
                return '-';
            }

            $minLabel = rtrim(rtrim(number_format((float) $minimum, 2, '.', ''), '0'), '.');
            $maxLabel = rtrim(rtrim(number_format((float) $maximum, 2, '.', ''), '0'), '.');

            return $minLabel.' - '.$maxLabel;
        }

        return '-';
    }

    public function warningLabel(object $row): string
    {
        return (bool) ($row->is_warning ?? false) ? 'Warning' : '-';
    }

    public function dateTimeOrDash(mixed $value, string $format = 'Y-m-d H:i:s'): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (\Throwable) {
            return '-';
        }
    }
}
