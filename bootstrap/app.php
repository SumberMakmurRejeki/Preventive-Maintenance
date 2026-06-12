<?php

use App\Http\Middleware\EnsurePrimeAuthenticated;
use App\Http\Middleware\EnsureOperatorSessionLifetime;
use App\Http\Middleware\EnsurePrimeRole;
use App\Http\Middleware\RedirectIfPrimeAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'prime.auth' => EnsurePrimeAuthenticated::class,
            'prime.guest' => RedirectIfPrimeAuthenticated::class,
            'prime.role' => EnsurePrimeRole::class,
            'prime.operator.session' => EnsureOperatorSessionLifetime::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
