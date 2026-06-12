<?php

namespace App\Http\Middleware;

use App\Services\Auth\PrimeAuthService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrimeAuthenticated
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
        if (! $this->primeAuth->isAuthenticated($request)) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
