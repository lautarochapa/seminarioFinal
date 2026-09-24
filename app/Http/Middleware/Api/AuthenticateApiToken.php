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

        if ($token) {
            $user = $this->tokens->authenticate($token);
            Auth::guard()->setUser($user);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
        }

        if ($request->header('X-CCC-Client') === 'android' && $request->user() && !$request->user()->canUseMobile()) {
            throw new \App\Exceptions\Auth\AuthException('AUTH_WEB_ONLY', 'Esta cuenta es exclusiva de la web.', 403);
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
