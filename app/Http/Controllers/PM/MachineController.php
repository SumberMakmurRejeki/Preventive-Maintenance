<?php

namespace App\Http\Controllers\PM;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Services\Auth\PrimeAuthService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MachineController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
    ) {
    }

    public function show(Request $request, Machine $machine): View
    {
        $machine->load('location');

        return view('pages.pm.machine-show', [
            'machine' => $machine,
            'role' => $this->primeAuth->role($request),
            'actorName' => $this->primeAuth->name($request),
        ]);
    }
}
