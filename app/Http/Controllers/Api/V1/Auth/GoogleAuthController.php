<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\GoogleRequest;
use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Services\Auth\ApiTokenService;
use App\Services\Auth\AuthService;

class GoogleAuthController extends Controller
{
    private $authService;
    private $tokenService;

    public function __construct(AuthService $authService, ApiTokenService $tokenService)
    {
        $this->authService = $authService;
        $this->tokenService = $tokenService;
    }

    public function authenticate(GoogleRequest $request)
    {
        $user    = $this->authService->googleAuth($request->input('token'), $request);
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => new UserResource($user),
            'token'    => $this->tokenService->issue($user, 'google'),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
