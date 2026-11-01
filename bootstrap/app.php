<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Force all API routes to accept JSON responses
        // This prevents redirect responses on validation failures
        $middleware->api(prepend: [
            // Lets same-origin requests (the Blade+Vue web app) authenticate via a
            // Sanctum session cookie instead of a bearer token. External/API clients
            // are unaffected and keep using bearer tokens.
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Shared\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'owner' => \App\Shared\Http\Middleware\AuthorizeUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Laravel will automatically return JSON for API routes
        // No custom handling needed
    })->create();
