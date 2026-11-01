<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeUser
{
    /**
     * Handle an incoming request.
     *
     * Reads the model strictly from the resolved route binding — never from
     * `$request->{$parameter}`, which checks request input (query/body) before
     * falling back to the route, letting a body/query field with the same name
     * as the route parameter shadow the real bound model.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $parameter)
    {
        $route = $request->route();
        $hasParameter = $route && in_array($parameter, $route->parameterNames(), true);

        // Route doesn't declare this parameter at all (index/store) — nothing to check yet.
        if (! $hasParameter) {
            return $next($request);
        }

        $model = $route->parameter($parameter);

        // Declared but didn't resolve to a model — fail closed rather than let it through.
        if (! $model instanceof Model) {
            abort(404);
        }

        $user = $request->user();
        if (! $user) {
            abort(401, 'non authenticated');
        }

        $ownerId = $model->user_id ?? $model->owner_id ?? null;
        if ($ownerId === null) {
            abort(403, 'Ownership cannot be determined for this resource.');
        }

        if ((int) $ownerId !== (int) $user->id) {
            abort(403, 'Unauthorised access');
        }

        return $next($request);
    }
}
