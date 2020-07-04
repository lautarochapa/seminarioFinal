<?php

namespace App\Http\Middleware;

use Closure;

class NutricionistaMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
    if ($request->user() && $request->user()->nivel_acceso  != '5')
    {
        if ($request->user()->nivel_acceso  != '3')
        {
            return redirect('/');
        }else{
            return $next($request);
        }
    }
    return $next($request);
    }
}
