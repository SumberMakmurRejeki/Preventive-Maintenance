<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\ActivityLogService;
use App\Services\Auth\PrimeAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected ActivityLogService $activityLog,
    ) {
    }

    public function create(): View
    {
        return view('pages.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $user = User::query()
            ->where('username', $credentials['username'])
            ->first();

        if ($user && ! $user->is_active) {
            return back()
                ->withInput($request->safe()->only('username'))
                ->withErrors([
                    'username' => 'Akun ini sedang nonaktif.',
                ]);
        }

        if (! Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            return back()
                ->withInput($request->safe()->only('username'))
                ->withErrors([
                    'username' => 'Username atau password tidak valid.',
                ]);
        }

        $request->session()->regenerate();
        $this->primeAuth->logoutGuest($request);

        /** @var User $authenticatedUser */
        $authenticatedUser = $request->user();
        $authenticatedUser->forceFill([
            'last_login_at' => now(),
        ])->save();

        $this->activityLog->log(
            request: $request,
            moduleName: 'auth',
            action: 'login',
            description: sprintf('%s login success', $authenticatedUser->role),
            tableName: 'users',
            recordId: $authenticatedUser->id,
        );

        $redirectTo = $request->session()->pull('redirect_to');

        if ($authenticatedUser->role === 'operator') {
            $request->session()->put('operator_session_started_at', now()->toIso8601String());
        } else {
            $request->session()->forget('operator_session_started_at');
        }

        if (is_string($redirectTo) && $redirectTo !== '') {
            return redirect()->to($redirectTo);
        }

        return redirect()->route(
            $authenticatedUser->role === 'admin' ? 'dashboard' : 'machine-access',
        );
    }
}
