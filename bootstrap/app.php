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
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\LogApiRequests::class, // Log all API requests
        ]);

        $middleware->alias([
            'owner' => \App\Http\Middleware\AuthorizeUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Report all exceptions with enhanced context
        $exceptions->report(function (Throwable $e) {
            // Add request context to all exception logs
            $context = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            // Add request details if available
            if (request()->hasSession()) {
                $context['request'] = [
                    'method' => request()->method(),
                    'url' => request()->fullUrl(),
                    'ip' => request()->ip(),
                    'user_id' => request()->user()?->id,
                ];
            }

            // Log with appropriate level based on exception type
            if ($e instanceof \App\Exceptions\FinTrackException) {
                logger()->error('FinTrack Exception', $context);
            } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                logger()->warning('Authentication Failed', $context);
            } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
                logger()->info('Validation Failed', array_merge($context, [
                    'errors' => $e->errors(),
                ]));
            } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                logger()->warning('HTTP Exception', array_merge($context, [
                    'status_code' => $e->getStatusCode(),
                ]));
            } else {
                logger()->error('Unhandled Exception', $context);
            }
        });

        // Render custom exceptions with proper JSON responses
        $exceptions->renderable(function (\App\Exceptions\FinTrackException $e) {
            return $e->render();
        });

        // Handle ModelNotFoundException gracefully
        $exceptions->renderable(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Resource not found',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        });

        // Handle Authorization exceptions
        $exceptions->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'You do not have permission to perform this action',
                'error_code' => 'UNAUTHORIZED',
            ], 403);
        });

        // Handle Throttle exceptions
        $exceptions->renderable(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e) {
            return response()->json([
                'message' => 'Too many requests. Please slow down.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
            ], 429);
        });

        // Laravel will automatically return JSON for API routes for other exceptions
    })->create();
