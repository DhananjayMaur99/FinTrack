<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Log all API requests with comprehensive context
 * 
 * This middleware logs:
 * - Request details (method, URL, IP, user agent)
 * - User context (authenticated user)
 * - Request payload (for debugging)
 * - Response status and duration
 * - Errors and exceptions
 */
class LogApiRequests
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Generate unique request ID for tracking
        $requestId = uniqid('req_', true);
        $request->attributes->set('request_id', $requestId);

        // Log incoming request
        $this->logRequest($request, $requestId);

        // Process request
        $response = $next($request);

        // Calculate duration
        $duration = round((microtime(true) - $startTime) * 1000, 2); // in milliseconds

        // Log response
        $this->logResponse($request, $response, $requestId, $duration);

        return $response;
    }

    /**
     * Log incoming request details
     */
    private function logRequest(Request $request, string $requestId): void
    {
        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
        ];

        // Log request payload for non-GET requests (exclude sensitive data)
        if (!$request->isMethod('GET')) {
            $payload = $request->except(['password', 'password_confirmation', 'current_password']);
            if (!empty($payload)) {
                $context['payload'] = $payload;
            }
        }

        Log::info('API Request', $context);
    }

    /**
     * Log response details
     */
    private function logResponse(Request $request, Response $response, string $requestId, float $duration): void
    {
        $statusCode = $response->getStatusCode();
        $level = $this->getLogLevel($statusCode);

        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->path(),
            'status' => $statusCode,
            'duration_ms' => $duration,
            'user_id' => $request->user()?->id,
        ];

        // Add response data for error responses
        if ($statusCode >= 400) {
            $responseContent = $response->getContent();
            if ($responseContent) {
                $context['response'] = json_decode($responseContent, true);
            }
        }

        // Log slow requests (> 1000ms)
        if ($duration > 1000) {
            Log::warning('Slow API Request', array_merge($context, [
                'warning' => 'Request took longer than 1 second',
            ]));
        }

        Log::log($level, 'API Response', $context);
    }

    /**
     * Determine log level based on status code
     */
    private function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            default => 'info',
        };
    }
}
