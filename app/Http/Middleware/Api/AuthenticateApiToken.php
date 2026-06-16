<?php

namespace App\Http\Middleware\Api;

use App\Services\Auth\ApiTokenService;
use Closure;

class AuthenticateApiToken
{
    private $tokens;

    public function __construct(ApiTokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function handle($request, Closure $next)
    {
        if (!$request->user() && $request->bearerToken()) {
            $user = $this->tokens->authenticate($request->bearerToken());
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
        }

        return $next($request);
    }
}
