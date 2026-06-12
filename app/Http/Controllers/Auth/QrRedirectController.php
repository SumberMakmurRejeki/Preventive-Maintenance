<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Services\Auth\ActivityLogService;
use App\Services\Auth\PrimeAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QrRedirectController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected ActivityLogService $activityLog,
    ) {}

    public function __invoke(Request $request, string $qrToken): RedirectResponse
    {
        $machine = Machine::query()->where('qr_token', $qrToken)->first();

        if (! $machine) {
            $this->activityLog->log(
                request: $request,
                moduleName: 'machine',
                action: 'machine_not_found',
                description: sprintf('QR token %s not found', $qrToken),
                tableName: 'machines',
            );

            return redirect()->route('machine-not-found');
        }

        $role = $this->primeAuth->role($request);

        if ($role === 'guest') {
            return redirect()->route('dashboard');
        }

        if (in_array($role, ['admin', 'operator'], true)) {
            if ($role === 'operator') {
                $request->session()->put('machine_access_return_url', route('machine-access'));
            }

            return redirect()->route('machines.show', $machine);
        }

        $request->session()->put('redirect_to', route('machines.show', $machine));

        $this->activityLog->log(
            request: $request,
            moduleName: 'machine',
            action: 'scan_qr',
            description: sprintf('QR scanned for machine %s before login', $machine->machine_code),
            tableName: 'machines',
            recordId: $machine->id,
        );

        return redirect()->route('login');
    }
}
