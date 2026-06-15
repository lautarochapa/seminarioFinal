<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $request)
    {
        $user    = $this->authService->register($request->validated(), $request);
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => new UserResource($user),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function login(LoginRequest $request)
    {
        $user    = $this->authService->login($request->validated(), $request);
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => new UserResource($user),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request);
        $traceId = $request->attributes->get('trace_id');

        return response()->noContent()->header('X-Trace-Id', $traceId);
    }

    public function me(Request $request)
    {
        $user    = $this->authService->me($request->user());
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => new UserResource($user),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user    = $this->authService->updateProfile($request->user(), $request->validated(), $request);
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => new UserResource($user),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
