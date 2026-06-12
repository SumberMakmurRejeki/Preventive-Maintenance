<?php

namespace App\Services\Report;

use App\Exports\Breakdown\ReportBreakdownExport;
use App\Exports\PM\ReportPmExport;
use App\Models\Breakdown;
use App\Models\ReportExport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ReportExportService
{
    public function __construct(
        protected ReportPmService $reportPmService,
        protected ReportBreakdownService $reportBreakdownService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportPmPdf(User $admin, Collection $rows, array $filters): ReportExport
    {
        $reportExport = ReportExport::query()->create([
            'report_type' => 'pm',
            'file_type' => 'pdf',
            'status' => 'processing',
            'filter_data' => $filters,
            'requested_by' => $admin->id,
            'requested_by_name_snapshot' => $admin->name,
            'requested_at' => now(),
        ]);

        $filename = sprintf('report-pm-%s.pdf', now()->format('Ymd-His'));
        $path = 'exports/'.$filename;

        try {
            $summary = [
                'total' => $rows->count(),
                'warning' => $rows->where('is_warning', true)->count(),
                'scheduled' => $rows->filter(fn ($row) => $this->reportPmService->statusLabel($row) === 'Scheduled')->count(),
                'in_progress' => $rows->filter(fn ($row) => $this->reportPmService->statusLabel($row) === 'In Progress')->count(),
                'waiting_review' => $rows->filter(fn ($row) => $this->reportPmService->statusLabel($row) === 'Waiting Review')->count(),
                'approved' => $rows->filter(fn ($row) => $this->reportPmService->statusLabel($row) === 'Approved')->count(),
                'overdue' => $rows->filter(fn ($row) => $this->reportPmService->statusLabel($row) === 'Overdue')->count(),
                'missed' => $rows->filter(fn ($row) => $this->reportPmService->statusLabel($row) === 'Missed')->count(),
            ];

            $pdf = Pdf::loadView('pages.report.pm.pdf', [
                'rows' => $rows,
                'reportPmService' => $this->reportPmService,
                'filters' => $filters,
                'summary' => $summary,
                'printedBy' => $admin->name,
            ])->setPaper('a4', 'landscape');

            Storage::disk('public')->put($path, $pdf->output());
        } catch (Throwable $exception) {
            $this->markAsFailed($reportExport, $exception->getMessage());
            throw $exception;
        }

        $reportExport->forceFill([
            'status' => 'completed',
            'file_name' => $filename,
            'file_path' => $path,
            'completed_at' => now(),
        ])->save();

        return $reportExport;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportPmExcel(User $admin, Collection $rows, array $filters): ReportExport
    {
        $reportExport = ReportExport::query()->create([
            'report_type' => 'pm',
            'file_type' => 'excel',
            'status' => 'processing',
            'filter_data' => $filters,
            'requested_by' => $admin->id,
            'requested_by_name_snapshot' => $admin->name,
            'requested_at' => now(),
        ]);

        $filename = sprintf('report-pm-%s.xlsx', now()->format('Ymd-His'));
        $path = 'exports/'.$filename;

        try {
            Excel::store(
                new ReportPmExport($rows, $this->reportPmService),
                $path,
                'public',
            );
        } catch (Throwable $exception) {
            $this->markAsFailed($reportExport, $exception->getMessage());
            throw $exception;
        }

        $reportExport->forceFill([
            'status' => 'completed',
            'file_name' => $filename,
            'file_path' => $path,
            'completed_at' => now(),
        ])->save();

        return $reportExport;
    }

    /**
     * @param  Collection<int, Breakdown>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function exportBreakdownPdf(User $admin, Collection $rows, array $filters): ReportExport
    {
        $reportExport = ReportExport::query()->create([
            'report_type' => 'breakdown',
            'file_type' => 'pdf',
            'status' => 'processing',
            'filter_data' => $filters,
            'requested_by' => $admin->id,
            'requested_by_name_snapshot' => $admin->name,
            'requested_at' => now(),
        ]);

        $filename = sprintf('report-breakdown-%s.pdf', now()->format('Ymd-His'));
        $path = 'exports/'.$filename;

        try {
            $summary = [
                'open' => $rows->where('status', 'open')->count(),
                'closed' => $rows->where('status', 'closed')->count(),
                'downtime_hours' => round($rows->sum(function (Breakdown $breakdown): float {
                    if ($breakdown->status === 'closed') {
                        return ((int) ($breakdown->downtime_minutes ?? 0)) / 60;
                    }

                    if (! $breakdown->breakdown_at) {
                        return 0;
                    }

                    return $breakdown->breakdown_at->diffInMinutes(now()) / 60;
                }), 1),
            ];

            $pdf = Pdf::loadView('pages.report.breakdown.pdf', [
                'rows' => $rows,
                'reportBreakdownService' => $this->reportBreakdownService,
                'filters' => $filters,
                'summary' => $summary,
                'printedBy' => $admin->name,
            ])->setPaper('a4', 'landscape');

            Storage::disk('public')->put($path, $pdf->output());
        } catch (Throwable $exception) {
            $this->markAsFailed($reportExport, $exception->getMessage());
            throw $exception;
        }

        $reportExport->forceFill([
            'status' => 'completed',
            'file_name' => $filename,
            'file_path' => $path,
            'completed_at' => now(),
        ])->save();

        return $reportExport;
    }

    /**
     * @param  Collection<int, Breakdown>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function exportBreakdownExcel(User $admin, Collection $rows, array $filters): ReportExport
    {
        $reportExport = ReportExport::query()->create([
            'report_type' => 'breakdown',
            'file_type' => 'excel',
            'status' => 'processing',
            'filter_data' => $filters,
            'requested_by' => $admin->id,
            'requested_by_name_snapshot' => $admin->name,
            'requested_at' => now(),
        ]);

        $filename = sprintf('report-breakdown-%s.xlsx', now()->format('Ymd-His'));
        $path = 'exports/'.$filename;

        try {
            Excel::store(
                new ReportBreakdownExport($rows, $this->reportBreakdownService),
                $path,
                'public',
            );
        } catch (Throwable $exception) {
            $this->markAsFailed($reportExport, $exception->getMessage());
            throw $exception;
        }

        $reportExport->forceFill([
            'status' => 'completed',
            'file_name' => $filename,
            'file_path' => $path,
            'completed_at' => now(),
        ])->save();

        return $reportExport;
    }

    public function downloadPath(ReportExport $reportExport): ?string
    {
        if ($reportExport->status !== 'completed' || ! $reportExport->file_path) {
            return null;
        }

        if (! Storage::disk('public')->exists($reportExport->file_path)) {
            return null;
        }

        return Storage::disk('public')->path($reportExport->file_path);
    }

    protected function markAsFailed(ReportExport $reportExport, string $message): void
    {
        $reportExport->forceFill([
            'status' => 'failed',
            'failed_message' => mb_substr($message, 0, 1000),
            'completed_at' => now(),
        ])->save();
    }
}
