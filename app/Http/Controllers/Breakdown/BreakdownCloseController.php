<?php

namespace App\Http\Controllers\Breakdown;

use App\Http\Controllers\Controller;
use App\Http\Requests\Breakdown\CloseBreakdownRequest;
use App\Models\Breakdown;
use App\Services\Auth\PrimeAuthService;
use App\Services\Breakdown\BreakdownService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BreakdownCloseController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected BreakdownService $breakdownService,
    ) {
    }

    public function show(Request $request, Breakdown $breakdown): View|RedirectResponse
    {
        $breakdown->load('machine.location');

        if ($breakdown->machine && ! $breakdown->machine->is_active && $breakdown->status === 'open') {
            return redirect()
                ->route('machines.show', $breakdown->machine)
                ->with('flash_error', 'Mesin nonaktif. Close breakdown tidak dapat diproses.');
        }

        return view('pages.breakdown.close', [
            'actorName' => $this->primeAuth->name($request),
            'role' => $this->primeAuth->role($request),
            'breakdown' => $breakdown,
        ]);
    }

    public function update(CloseBreakdownRequest $request, Breakdown $breakdown): RedirectResponse
    {
        if ($breakdown->status !== 'open') {
            return redirect()
                ->route('machines.show', $breakdown->machine)
                ->with('flash_error', 'Breakdown sudah ditutup.');
        }

        $actor = $this->primeAuth->authenticatedUser();
        if (! $actor) {
            return redirect()->route('login');
        }

        try {
            $this->breakdownService->close(
                request: $request,
                breakdown: $breakdown,
                actor: $actor,
                payload: $request->validated(),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('machines.show', $breakdown->machine)
            ->with('flash_success', 'Close breakdown berhasil diproses.');
    }
}

