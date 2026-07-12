<?php

namespace App\Http\Middleware\Api;

use App\Services\Auth\ApiTokenService;
use Closure;
use Illuminate\Support\Facades\Auth;

class AuthenticateApiToken
{
    private $tokens;

    public function __construct(ApiTokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function handle($request, Closure $next)
    {
        $token = $this->bearerToken($request);

        if (!$request->user() && $token) {
            $user = $this->tokens->authenticate($token);
            Auth::guard()->setUser($user);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
        }

        return $next($request);
    }

    private function bearerToken($request)
    {
        $token = $request->bearerToken();
        if ($token) {
            return $token;
        }

        $header = $request->server('HTTP_AUTHORIZATION')
            ?: $request->server('REDIRECT_HTTP_AUTHORIZATION')
            ?: $request->headers->get('Authorization');

        if (!$header && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

        if (!$header || stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        return trim(substr($header, 7));
    }
}
