<?php

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\OnboardingStatusService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    private $service;

    public function __construct(OnboardingStatusService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => $this->service->forUser($request->user()->id),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
