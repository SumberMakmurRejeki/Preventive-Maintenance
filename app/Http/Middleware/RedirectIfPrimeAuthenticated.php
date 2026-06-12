<?php

namespace App\Http\Middleware;

use App\Services\Auth\PrimeAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfPrimeAuthenticated
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $role = $this->primeAuth->role($request);

        return match ($role) {
            'admin', 'guest' => redirect()->route('dashboard'),
            'operator' => redirect()->route('machine-access'),
            default => $next($request),
        };
    }
}
