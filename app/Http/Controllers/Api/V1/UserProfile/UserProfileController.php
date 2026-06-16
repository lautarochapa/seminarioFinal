<?php

namespace App\Http\Controllers\Api\V1\UserProfile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserProfile\UpdateUserProfileRequest;
use App\Http\Resources\Api\V1\UserProfile\UserProfileResource;
use App\Services\UserProfile\UserProfileService;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    private $service;

    public function __construct(UserProfileService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->show($request->user()->id);

        return response()->json([
            'data'     => new UserProfileResource($data),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateUserProfileRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->update(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new UserProfileResource($data),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
