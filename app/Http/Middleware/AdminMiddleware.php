<?php

namespace App\Http\Middleware;

use Closure;

class AdminMiddleware
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
    if ($request->user() && $request->user()->nivel_acceso == '2')
    {
        return $next($request);
    }else{
        if ($request->user() && $request->user()->nivel_acceso == '3')
        {
            return $next($request);
        }else{
            return redirect('/');
        } 
    }
}
