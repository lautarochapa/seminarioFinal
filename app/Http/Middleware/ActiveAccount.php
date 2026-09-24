<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class ActiveAccount
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        if ($user && ($user->status !== 'active' || $user->trashed())) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            abort(401, 'La cuenta no esta activa.');
        }
        return $next($request);
    }
}
