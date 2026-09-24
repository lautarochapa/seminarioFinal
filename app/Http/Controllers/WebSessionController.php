<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

class WebSessionController extends Controller
{
    public function login(LoginRequest $request, AuthService $auth)
    {
        return $this->signedIn($auth->login($request->validated(), $request), $request);
    }

    public function register(RegisterRequest $request, AuthService $auth)
    {
        return $this->signedIn($auth->register($request->validated(), $request), $request, 201);
    }

    public function logout(Request $request, AuthService $auth)
    {
        $auth->logout($request);
        return response()->noContent();
    }

    private function signedIn($user, Request $request, $status = 200)
    {
        return response()->json([
            'data' => new UserResource($user),
            'csrf_token' => $request->session()->token(),
            'trace_id' => $request->attributes->get('trace_id'),
        ], $status)->header('Cache-Control', 'no-store, private');
    }
}
