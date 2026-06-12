<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MachineAccessRequest;
use App\Models\Machine;
use App\Services\Auth\ActivityLogService;
use App\Services\Auth\PrimeAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MachineAccessController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLog,
        protected PrimeAuthService $primeAuth,
    ) {}

    public function create(Request $request): View
    {
        return view('pages.auth.machine-access', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
        ]);
    }

    public function store(MachineAccessRequest $request): RedirectResponse
    {
        $machine = Machine::query()
            ->where('machine_code', $request->validated('machine_code'))
            ->first();

        if (! $machine) {
            $this->activityLog->log(
                request: $request,
                moduleName: 'machine',
                action: 'machine_not_found',
                description: sprintf('Manual machine code %s not found', $request->validated('machine_code')),
                tableName: 'machines',
            );

            return back()->withErrors([
                'machine_code' => 'Kode mesin tidak ditemukan.',
            ]);
        }

        $request->session()->put('machine_access_return_url', route('machine-access'));

        return redirect()->route('machines.show', $machine);
    }
}
