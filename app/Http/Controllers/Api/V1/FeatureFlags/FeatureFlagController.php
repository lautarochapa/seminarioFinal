<?php

namespace App\Http\Controllers\Api\V1\FeatureFlags;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FeatureFlags\UpdateFeatureFlagRequest;
use App\Http\Resources\Api\V1\FeatureFlags\FeatureFlagResource;
use App\Services\FeatureFlags\FeatureFlagService;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    private $service;

    public function __construct(FeatureFlagService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => FeatureFlagResource::collection($this->service->list($request->query())),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateFeatureFlagRequest $request, $key)
    {
        $flag = $this->service->update(
            $request->user()->id,
            $key,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => new FeatureFlagResource($flag),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
