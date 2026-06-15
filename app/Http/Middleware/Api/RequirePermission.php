<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;

class RequirePermission
{
    public function handle($request, Closure $next, $permission)
    {
        $user = $request->user();

        if (!$user || !$user->hasPermission($permission)) {
            throw new AuthorizationException('Sin permiso para esta acción.');
        }

        return $next($request);
    }
}
