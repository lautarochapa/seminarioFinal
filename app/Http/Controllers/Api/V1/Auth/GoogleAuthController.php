<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\GoogleRequest;
use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Services\Auth\AuthService;

class GoogleAuthController extends Controller
{
    private $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function authenticate(GoogleRequest $request)
    {
        $user    = $this->authService->googleAuth($request->input('token'), $request);
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => new UserResource($user),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
