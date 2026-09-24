<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    protected function inExceptArray($request)
    {
        // Browser session writes need CSRF protection; Android continues using bearer tokens.
        if ($request->is('api/v1/*') && !$request->bearerToken() && $request->user()) {
            return false;
        }
        return parent::inExceptArray($request);
    }

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'api/v1/*',
        'auth/*',
        'admin/*',
        'family-groups',
        'family-groups/*',
        'users/me/*',
    ];
}
