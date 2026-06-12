<?php

namespace App\Http\Controllers\PM;

use App\Http\Controllers\Controller;
use App\Models\Breakdown;
use App\Models\Machine;
use App\Services\Auth\PrimeAuthService;
use App\Services\PM\MachineLandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MachineLandingController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected MachineLandingService $machineLandingService,
    ) {}

    public function show(Request $request, Machine $machine): View
    {
        $machine->load('location');

        $landingData = $this->machineLandingService->build($machine);
        $role = $this->primeAuth->role($request);
        $backUrl = $role === 'operator'
            ? (string) $request->session()->get('machine_access_return_url', route('machine-access'))
            : url()->previous();

        $pmExecutorDisabledReason = null;
        $transactionDisabledReason = null;

        if (! $machine->is_active) {
            $transactionDisabledReason = 'Mesin nonaktif. Transaksi baru tidak dapat dibuat. Mesin nonaktif tetap bisa dilihat, tetapi transaksi baru harus disabled.';
        } elseif ($role === 'admin') {
            $pmExecutorDisabledReason = 'Admin tidak mengerjakan PM Executor.';
        } elseif ($role === 'operator' && ! $landingData['hasActivePmSchedule']) {
            $pmExecutorDisabledReason = 'Tidak ada jadwal PM aktif.';
        }

        return view('pages.machine.show', [
            'machine' => $machine,
            'role' => $role,
            'actorName' => $this->primeAuth->name($request),
            'lastPm' => $landingData['lastPm'],
            'nextPm' => $landingData['nextPm'],
            'hasActivePmSchedule' => $landingData['hasActivePmSchedule'],
            'openBreakdowns' => $landingData['openBreakdowns'],
            'partOptions' => $landingData['partOptions'],
            'pmExecutorDisabledReason' => $pmExecutorDisabledReason,
            'transactionDisabledReason' => $transactionDisabledReason,
            'backUrl' => $backUrl,
        ]);
    }

    public function openPmExecutor(Request $request, Machine $machine): RedirectResponse
    {
        $landingData = $this->machineLandingService->build($machine);

        if (! $machine->is_active) {
            return back()->with('flash_error', 'Mesin nonaktif. PM Executor tidak dapat dibuka.');
        }

        if ($this->primeAuth->role($request) !== 'operator') {
            return back()->with('flash_error', 'Hanya operator yang dapat mengakses PM Executor.');
        }

        if (! $landingData['hasActivePmSchedule']) {
            return back()->with('flash_error', 'Tidak ada jadwal PM aktif untuk mesin ini.');
        }

        return redirect()->route('pm-executor.show', $machine);
    }

    public function openBreakdownInput(Machine $machine): RedirectResponse
    {
        if (! $machine->is_active) {
            return back()->with('flash_error', 'Mesin nonaktif. Input breakdown tidak dapat dibuka.');
        }

        return back()->with('flash_info', 'Input breakdown akan dilanjutkan pada FASE 13.');
    }

    public function closeBreakdown(Breakdown $breakdown): RedirectResponse
    {
        if ($breakdown->status !== 'open') {
            return back()->with('flash_error', 'Breakdown ini sudah CLOSED.');
        }

        if ($breakdown->machine && ! $breakdown->machine->is_active) {
            return back()->with('flash_error', 'Mesin nonaktif. Close breakdown tidak dapat diproses.');
        }

        return back()->with('flash_info', 'Close breakdown akan dilanjutkan pada FASE 14.');
    }
}
