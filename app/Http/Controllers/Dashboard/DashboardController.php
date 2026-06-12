<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardFilterRequest;
use App\Models\Breakdown;
use App\Models\Machine;
use App\Models\PmScheduleDate;
use App\Services\Auth\PrimeAuthService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
    ) {}

    public function __invoke(DashboardFilterRequest $request): View
    {
        $normalizedRange = $request->normalizedDateRange();
        $startDate = Carbon::createFromFormat('Y-m-d', $normalizedRange['start_date'])->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $normalizedRange['end_date'])->endOfDay();

        $dashboardPayload = [
            'machineSummary' => [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
            ],
            'pmStatus' => [
                'completed' => 0,
                'overdue' => 0,
                'total' => 0,
            ],
            'mtbfHours' => 0.0,
            'mtbrHours' => 0.0,
            'pmRateSeries' => collect(),
            'breakdownDurationSeries' => collect(),
            'closedBreakdownLogs' => collect(),
            'loadErrorMessage' => null,
        ];

        try {
            $dashboardPayload = $this->buildDashboardPayload($startDate, $endDate);
        } catch (Throwable $exception) {
            Log::error('Dashboard monitoring failed to load.', [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'error' => $exception->getMessage(),
            ]);

            $dashboardPayload['loadErrorMessage'] = 'Data monitoring gagal dimuat. Silakan coba lagi.';
        }

        return view('pages.dashboard.index', [
            'role' => $this->primeAuth->role($request),
            'actorName' => $this->primeAuth->name($request),
            'dateRange' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            ...$dashboardPayload,
        ]);
    }

    protected function buildDashboardPayload(Carbon $startDate, Carbon $endDate): array
    {
        $machineSummary = $this->machineSummary();
        $pmStatus = $this->pmStatus($startDate, $endDate);
        $pmRateSeries = $this->pmRateSeries($startDate, $endDate);
        $breakdownInsights = $this->breakdownInsights($startDate, $endDate);

        return [
            'machineSummary' => $machineSummary,
            'pmStatus' => $pmStatus,
            'mtbfHours' => $breakdownInsights['mtbfHours'],
            'mtbrHours' => $breakdownInsights['mtbrHours'],
            'pmRateSeries' => $pmRateSeries,
            'breakdownDurationSeries' => $breakdownInsights['durationSeries'],
            'closedBreakdownLogs' => $breakdownInsights['closedLogs'],
            'loadErrorMessage' => null,
        ];
    }

    protected function machineSummary(): array
    {
        $total = Machine::query()->count();
        $active = Machine::query()->active()->count();
        $inactive = Machine::query()->inactive()->count();

        return compact('total', 'active', 'inactive');
    }

    protected function pmStatus(Carbon $startDate, Carbon $endDate): array
    {
        $scheduleDates = PmScheduleDate::query()
            ->whereBetween('scheduled_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get(['status']);

        $completed = $scheduleDates->whereIn('status', ['approved', 'waiting_review'])->count();
        $overdue = $scheduleDates->whereIn('status', ['overdue', 'missed'])->count();

        return [
            'completed' => $completed,
            'overdue' => $overdue,
            'total' => $completed + $overdue,
        ];
    }

    protected function pmRateSeries(Carbon $startDate, Carbon $endDate): Collection
    {
        $scheduleByMonth = PmScheduleDate::query()
            ->select(['scheduled_date', 'status'])
            ->whereBetween('scheduled_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy(fn (PmScheduleDate $scheduleDate): string => Carbon::parse((string) $scheduleDate->scheduled_date)->format('Y-m'));

        $periodMonths = collect();
        $cursor = $startDate->copy()->startOfMonth();
        $lastMonth = $endDate->copy()->startOfMonth();

        while ($cursor->lte($lastMonth)) {
            $periodMonths->push($cursor->format('Y-m'));
            $cursor->addMonth();
        }

        return $periodMonths->map(function (string $monthKey) use ($scheduleByMonth): array {
            $monthRows = $scheduleByMonth->get($monthKey, collect());
            $totalSchedules = $monthRows->count();
            $submittedSchedules = $monthRows->whereIn('status', ['waiting_review', 'approved'])->count();
            $actualPercent = $totalSchedules > 0 ? round(($submittedSchedules / $totalSchedules) * 100, 1) : 0.0;
            $targetPercent = $totalSchedules > 0 ? 100.0 : 0.0;

            return [
                'month_key' => $monthKey,
                'month_label' => Carbon::createFromFormat('Y-m', $monthKey)->translatedFormat('M'),
                'target_percentage' => $targetPercent,
                'actual_percentage' => $actualPercent,
            ];
        });
    }

    protected function breakdownInsights(Carbon $startDate, Carbon $endDate): array
    {
        $closedBreakdowns = Breakdown::query()
            ->with(['machine:id,machine_name'])
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$startDate, $endDate])
            ->orderBy('closed_at')
            ->get();

        if ($closedBreakdowns->isEmpty()) {
            return [
                'mtbfHours' => 0.0,
                'mtbrHours' => 0.0,
                'durationSeries' => collect(),
                'closedLogs' => collect(),
            ];
        }

        $durationSeries = $closedBreakdowns
            ->groupBy(fn (Breakdown $breakdown): string => $breakdown->part_name_snapshot ?: $breakdown->custom_part_name ?: 'Part')
            ->map(function (Collection $items, string $partName): array {
                return [
                    'part_name' => $partName,
                    'total_duration_hours' => round($items->sum(fn (Breakdown $breakdown): float => ($breakdown->downtime_minutes ?? 0) / 60), 1),
                    'total_breakdown_count' => $items->count(),
                ];
            })
            ->sortByDesc('total_duration_hours')
            ->values();

        $closedLogs = $closedBreakdowns
            ->sortByDesc('closed_at')
            ->map(function (Breakdown $breakdown): array {
                $machineName = $breakdown->machine?->machine_name ?? $breakdown->machine_name_snapshot ?? 'Mesin Tanpa Nama';
                $title = (string) ($breakdown->problem ?: ($breakdown->part_name_snapshot ?: 'Breakdown Closed'));
                $partName = (string) ($breakdown->part_name_snapshot ?: ($breakdown->custom_part_name ?: 'Part'));

                return [
                    'title' => $title,
                    'machine_name' => $machineName,
                    'part_name' => $partName,
                    'closed_date_label' => optional($breakdown->closed_at)?->translatedFormat('d M Y') ?? '-',
                    'status' => 'Closed',
                ];
            })
            ->values();

        return [
            'mtbfHours' => $this->calculateMtbfHours($closedBreakdowns),
            'mtbrHours' => round($closedBreakdowns->avg(fn (Breakdown $breakdown): float => ($breakdown->downtime_minutes ?? 0) / 60), 1),
            'durationSeries' => $durationSeries,
            'closedLogs' => $closedLogs,
        ];
    }

    protected function calculateMtbfHours(Collection $breakdowns): float
    {
        $orderedByStart = $breakdowns
            ->filter(fn (Breakdown $breakdown): bool => $breakdown->breakdown_at !== null)
            ->sortBy('breakdown_at')
            ->values();

        if ($orderedByStart->count() < 2) {
            return 0.0;
        }

        $intervalHours = collect();

        for ($index = 1; $index < $orderedByStart->count(); $index++) {
            /** @var Breakdown $previous */
            $previous = $orderedByStart->get($index - 1);
            /** @var Breakdown $current */
            $current = $orderedByStart->get($index);

            $intervalHours->push($previous->breakdown_at->diffInMinutes($current->breakdown_at) / 60);
        }

        return round($intervalHours->avg() ?: 0.0, 1);
    }
}
