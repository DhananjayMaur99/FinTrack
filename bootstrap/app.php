<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Response; // <-- 1. ADD THIS
use Symfony\Component\HttpKernel\Exception\HttpException; // <-- 2. ADD THIS

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // --- THIS IS OUR NEW GLOBAL ERROR HANDLER ---
        $exceptions->renderable(function (Throwable $e, $request) {

            // We only want to do this for our API routes.
            if ($request->is('api/*')) {

                // Default error message and status code
                $message = 'Server Error';
                $statusCode = 500;

                // If it's a "known" HTTP exception (404, 403, 401, etc.)
                // we'll use its specific message and status code.
                if ($e instanceof HttpException) {
                    $message = $e->getMessage() ?: Response::$statusTexts[$e->getStatusCode()];
                    $statusCode = $e->getStatusCode();
                }

                // For all other errors, in production, we just show "Server Error"
                // In debug mode, we can show the actual error message.
                if (config('app.debug')) {
                    $message = $e->getMessage();
                }

                // Return our standard JSON error response
                return response()->json([
                    'message' => $message,
                ], $statusCode);
            }
        });

    })->create();
