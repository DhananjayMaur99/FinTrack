<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\Model;

class AuthorizeUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $parameter = null)
    {
        $model = null;

        if ($parameter) {
            $model = $request->route($parameter);
        } else {
            // autodetect first Eloquent model route param that looks like an owned resource
            foreach ($request->route()->parameters() as $param) {
                if ($param instanceof Model && (isset($param->user_id) || isset($param->owner_id))) {
                    $model = $param;
                    break;
                }
            }
        }

        // If no model bound (index/store), just continue.
        if (! $model) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $ownerId = $model->user_id ?? $model->owner_id ?? null;
        if ($ownerId === null) {
            abort(403, 'Ownership cannot be determined for this resource.');
        }

        if ($ownerId !== $user->id) {
            abort(403);
        }

        return $next($request);
    }
}
