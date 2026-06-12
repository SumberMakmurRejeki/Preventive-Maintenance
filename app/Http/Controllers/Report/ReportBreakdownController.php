<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportBreakdownRequest;
use App\Models\ReportExport;
use App\Services\Auth\ActivityLogService;
use App\Services\Auth\PrimeAuthService;
use App\Services\Report\ReportBreakdownService;
use App\Services\Report\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ReportBreakdownController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected ReportBreakdownService $reportBreakdownService,
        protected ReportExportService $reportExportService,
        protected ActivityLogService $activityLogService,
    ) {}

    public function index(ReportBreakdownRequest $request): View
    {
        $filters = $request->normalizedFilters();
        $loadError = false;
        $errorMessage = null;

        try {
            $data = $this->reportBreakdownService->list($filters);
        } catch (Throwable) {
            $loadError = true;
            $errorMessage = 'Data report breakdown gagal dimuat. Silakan refresh halaman.';
            $data = [
                'rows' => new LengthAwarePaginator(new Collection, 0, 10),
                'locations' => collect(),
            ];
        }

        $exports = ReportExport::query()
            ->whereIn('report_type', ['pm', 'breakdown'])
            ->latest('id')
            ->limit(15)
            ->get();

        return view('pages.report.breakdown.index', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'filters' => $filters,
            'rows' => $data['rows'],
            'locations' => $data['locations'],
            'exports' => $exports,
            'reportBreakdownService' => $this->reportBreakdownService,
            'loadError' => $loadError,
            'loadErrorMessage' => $errorMessage,
        ]);
    }

    public function exportPdf(ReportBreakdownRequest $request): JsonResponse|RedirectResponse
    {
        $filters = $request->normalizedFilters();
        $rows = $this->reportBreakdownService->exportRows($filters);

        if ($rows->isEmpty()) {
            return $this->exportEmptyResponse($request);
        }

        $admin = $this->primeAuth->authenticatedUser();
        if (! $admin) {
            throw ValidationException::withMessages([
                'auth' => 'User admin tidak ditemukan.',
            ]);
        }

        try {
            $reportExport = $this->reportExportService->exportBreakdownPdf($admin, $rows, $filters);
        } catch (Throwable) {
            return $this->exportFailedResponse($request);
        }

        $this->activityLogService->log(
            request: $request,
            moduleName: 'report_breakdown',
            action: 'export_pdf',
            description: 'Export Report Breakdown PDF',
            tableName: 'report_exports',
            recordId: $reportExport->id,
        );

        return $this->exportSuccessResponse($request, $reportExport);
    }

    public function exportExcel(ReportBreakdownRequest $request): JsonResponse|RedirectResponse
    {
        $filters = $request->normalizedFilters();
        $rows = $this->reportBreakdownService->exportRows($filters);

        if ($rows->isEmpty()) {
            return $this->exportEmptyResponse($request);
        }

        $admin = $this->primeAuth->authenticatedUser();
        if (! $admin) {
            throw ValidationException::withMessages([
                'auth' => 'User admin tidak ditemukan.',
            ]);
        }

        try {
            $reportExport = $this->reportExportService->exportBreakdownExcel($admin, $rows, $filters);
        } catch (Throwable) {
            return $this->exportFailedResponse($request);
        }

        $this->activityLogService->log(
            request: $request,
            moduleName: 'report_breakdown',
            action: 'export_excel',
            description: 'Export Report Breakdown Excel',
            tableName: 'report_exports',
            recordId: $reportExport->id,
        );

        return $this->exportSuccessResponse($request, $reportExport);
    }

    public function download(Request $request, ReportExport $reportExport): RedirectResponse|BinaryFileResponse
    {
        $path = $this->reportExportService->downloadPath($reportExport);
        if (! $path) {
            return redirect()
                ->route('report-breakdown.index')
                ->with('flash_error', 'File export tidak ditemukan atau belum siap.');
        }

        $filename = $reportExport->file_name ?: basename($path);

        return Response::download($path, $filename);
    }

    protected function exportEmptyResponse(ReportBreakdownRequest $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data breakdown untuk diexport.',
            ], 422);
        }

        return redirect()
            ->route('report-breakdown.index', $request->normalizedFilters())
            ->with('flash_warning', 'Tidak ada data breakdown untuk diexport.');
    }

    protected function exportSuccessResponse(ReportBreakdownRequest $request, ReportExport $reportExport): JsonResponse|RedirectResponse
    {
        $downloadUrl = route('report-breakdown.download', $reportExport->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'download_url' => $downloadUrl,
                'message' => 'Report Breakdown berhasil dibuat.',
            ]);
        }

        return redirect()
            ->route('report-breakdown.index', $request->normalizedFilters())
            ->with('flash_success', 'Report Breakdown berhasil dibuat.')
            ->with('download_url', $downloadUrl);
    }

    protected function exportFailedResponse(ReportBreakdownRequest $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Report Breakdown gagal dibuat. Silakan coba kembali.',
            ], 500);
        }

        return redirect()
            ->route('report-breakdown.index', $request->normalizedFilters())
            ->with('flash_error', 'Report Breakdown gagal dibuat. Silakan coba kembali.');
    }
}
