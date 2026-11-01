<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to force all API requests to expect JSON responses.
 * This prevents redirect responses on validation failures.
 */
class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force the request to expect JSON response
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
