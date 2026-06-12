<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportPmRequest;
use App\Models\ReportExport;
use App\Services\Auth\ActivityLogService;
use App\Services\Auth\PrimeAuthService;
use App\Services\Report\ReportExportService;
use App\Services\Report\ReportPmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ReportPmController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected ReportPmService $reportPmService,
        protected ReportExportService $reportExportService,
        protected ActivityLogService $activityLogService,
    ) {}

    public function index(ReportPmRequest $request): View
    {
        $filters = $request->normalizedFilters();
        $loadError = false;
        $errorMessage = null;

        try {
            $data = $this->reportPmService->list($filters);
        } catch (Throwable) {
            $loadError = true;
            $errorMessage = 'Data report PM gagal dimuat. Silakan refresh halaman.';
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

        return view('pages.report.pm.index', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'filters' => $filters,
            'rows' => $data['rows'],
            'locations' => $data['locations'],
            'exports' => $exports,
            'reportPmService' => $this->reportPmService,
            'loadError' => $loadError,
            'loadErrorMessage' => $errorMessage,
        ]);
    }

    public function exportPdf(ReportPmRequest $request): JsonResponse|RedirectResponse
    {
        $filters = $request->normalizedFilters();
        $rows = $this->reportPmService->exportRows($filters);

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
            $reportExport = $this->reportExportService->exportPmPdf($admin, $rows, $filters);
        } catch (Throwable) {
            return $this->exportFailedResponse($request);
        }

        $this->activityLogService->log(
            request: $request,
            moduleName: 'report_pm',
            action: 'export_pdf',
            description: 'Export Report PM PDF',
            tableName: 'report_exports',
            recordId: $reportExport->id,
        );

        return $this->exportSuccessResponse($request, $reportExport);
    }

    public function exportExcel(ReportPmRequest $request): JsonResponse|RedirectResponse
    {
        $filters = $request->normalizedFilters();
        $rows = $this->reportPmService->exportRows($filters);

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
            $reportExport = $this->reportExportService->exportPmExcel($admin, $rows, $filters);
        } catch (Throwable) {
            return $this->exportFailedResponse($request);
        }

        $this->activityLogService->log(
            request: $request,
            moduleName: 'report_pm',
            action: 'export_excel',
            description: 'Export Report PM Excel',
            tableName: 'report_exports',
            recordId: $reportExport->id,
        );

        return $this->exportSuccessResponse($request, $reportExport);
    }

    public function download(Request $request, ReportExport $reportExport)
    {
        $path = $this->reportExportService->downloadPath($reportExport);
        if (! $path) {
            return redirect()
                ->route('report-pm.index')
                ->with('flash_error', 'File export tidak ditemukan atau belum siap.');
        }

        $filename = $reportExport->file_name ?: basename($path);

        return Response::download($path, $filename);
    }

    protected function exportEmptyResponse(ReportPmRequest $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data PM untuk diexport.',
            ], 422);
        }

        return redirect()
            ->route('report-pm.index', $request->normalizedFilters())
            ->with('flash_warning', 'Tidak ada data PM untuk diexport.');
    }

    protected function exportSuccessResponse(ReportPmRequest $request, ReportExport $reportExport): JsonResponse|RedirectResponse
    {
        $downloadUrl = route('report-exports.download', $reportExport->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'download_url' => $downloadUrl,
                'message' => 'Report PM berhasil dibuat.',
            ]);
        }

        return redirect()
            ->route('report-pm.index', $request->normalizedFilters())
            ->with('flash_success', 'Report PM berhasil dibuat.')
            ->with('download_url', $downloadUrl);
    }

    protected function exportFailedResponse(ReportPmRequest $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Report PM gagal dibuat. Silakan coba kembali.',
            ], 500);
        }

        return redirect()
            ->route('report-pm.index', $request->normalizedFilters())
            ->with('flash_error', 'Report PM gagal dibuat. Silakan coba kembali.');
    }
}
