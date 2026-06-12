<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\ActivityLogService;
use App\Services\Auth\PrimeAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected ActivityLogService $activityLog,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $role = $this->primeAuth->role($request);
        $this->activityLog->log(
            request: $request,
            moduleName: 'auth',
            action: 'logout',
            description: sprintf('%s logout', $role ?? 'unknown'),
        );

        if (Auth::check()) {
            Auth::logout();
        }

        $this->primeAuth->logoutGuest($request);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
