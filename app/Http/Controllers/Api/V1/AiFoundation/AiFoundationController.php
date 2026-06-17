<?php

namespace App\Http\Controllers\Api\V1\AiFoundation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AiFoundation\TestSuggestionRequest;
use App\Http\Resources\Api\V1\FeatureFlags\FeatureFlagResource;
use App\Services\Ai\AiFoundationService;
use Illuminate\Http\Request;

class AiFoundationController extends Controller
{
    private $service;

    public function __construct(AiFoundationService $service)
    {
        $this->service = $service;
    }

    public function flag(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => new FeatureFlagResource($this->service->aiFlag()),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function testSuggestion(TestSuggestionRequest $request)
    {
        $result = $this->service->testSuggestion(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => $result,
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
