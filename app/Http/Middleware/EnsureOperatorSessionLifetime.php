<?php

namespace App\Http\Middleware;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperatorSessionLifetime
{
    private const OPERATOR_SESSION_KEY = 'operator_session_started_at';

    private const OPERATOR_MAX_MINUTES = 480;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->role !== 'operator') {
            return $next($request);
        }

        $startedAt = $request->session()->get(self::OPERATOR_SESSION_KEY);

        if (! is_string($startedAt) || $startedAt === '') {
            $request->session()->put(self::OPERATOR_SESSION_KEY, now()->toIso8601String());

            return $next($request);
        }

        $startedAtTime = CarbonImmutable::parse($startedAt);

        if ($startedAtTime->addMinutes(self::OPERATOR_MAX_MINUTES)->isPast()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            /** @var RedirectResponse $redirect */
            $redirect = redirect()->route('login');

            return $redirect->withErrors([
                'username' => 'Sesi operator sudah habis (8 jam). Silakan login kembali.',
            ]);
        }

        return $next($request);
    }
}

