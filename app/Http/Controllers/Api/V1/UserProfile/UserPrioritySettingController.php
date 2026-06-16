<?php

namespace App\Http\Controllers\Api\V1\UserProfile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserProfile\UpdatePrioritySettingsRequest;
use App\Http\Resources\Api\V1\UserProfile\UserPrioritySettingResource;
use App\Services\UserProfile\UserPrioritySettingService;
use Illuminate\Http\Request;

class UserPrioritySettingController extends Controller
{
    private $service;

    public function __construct(UserPrioritySettingService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $setting = $this->service->show($request->user()->id);

        return response()->json([
            'data'     => new UserPrioritySettingResource($setting),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function update(UpdatePrioritySettingsRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $setting = $this->service->update(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new UserPrioritySettingResource($setting),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
