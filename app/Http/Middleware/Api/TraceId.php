<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Support\Str;

class TraceId
{
    public function handle($request, Closure $next)
    {
        $incoming = $request->header('X-Trace-Id');
        $traceId  = ($incoming && Str::isUuid($incoming)) ? $incoming : (string) Str::uuid();

        $request->attributes->set('trace_id', $traceId);

        $response = $next($request);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
