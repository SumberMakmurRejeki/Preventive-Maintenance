<?php

namespace App\Http\Middleware;

use App\Services\Auth\ActivityLogService;
use App\Services\Auth\PrimeAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrimeRole
{
    public function __construct(
        protected PrimeAuthService $primeAuth,
        protected ActivityLogService $activityLog,
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $currentRole = $this->primeAuth->role($request);

        if ($currentRole === null) {
            return redirect()->route('login');
        }

        if (! in_array($currentRole, $roles, true)) {
            $this->activityLog->log(
                request: $request,
                moduleName: 'authorization',
                action: 'access_denied',
                description: sprintf(
                    'Role %s tried to access %s',
                    $currentRole,
                    $request->path(),
                ),
            );

            return redirect()->route('forbidden');
        }

        return $next($request);
    }
}
