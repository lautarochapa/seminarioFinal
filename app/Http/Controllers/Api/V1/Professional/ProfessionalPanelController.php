<?php

namespace App\Http\Controllers\Api\V1\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Professional\ProfessionalUpdateMealPlanRequest;
use App\Http\Resources\Api\V1\Professional\LinkedUserResource;
use App\Http\Resources\Api\V1\Professional\MealPlanResource;
use App\Http\Resources\Api\V1\UserProfile\UserProfileResource;
use App\Services\Professional\ProfessionalPanelService;
use Illuminate\Http\Request;

class ProfessionalPanelController extends Controller
{
    private $service;

    public function __construct(ProfessionalPanelService $service)
    {
        $this->service = $service;
    }

    public function linkedUsers(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $links   = $this->service->linkedUsers($request->user()->id);

        return response()->json([
            'data'     => LinkedUserResource::collection($links),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function userProfile(Request $request, $userId)
    {
        $traceId     = $request->attributes->get('trace_id');
        $profileData = $this->service->getUserProfile((int) $userId, $request->user()->id);

        return response()->json([
            'data'     => new UserProfileResource($profileData),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function mealPlans(Request $request, $userId)
    {
        $traceId = $request->attributes->get('trace_id');
        $plans   = $this->service->getMealPlans((int) $userId, $request->user()->id);

        return response()->json([
            'data'     => MealPlanResource::collection($plans),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function updateMealPlan(ProfessionalUpdateMealPlanRequest $request, $userId, $planId)
    {
        $traceId = $request->attributes->get('trace_id');
        $plan    = $this->service->updateMealPlan(
            (int) $userId,
            $request->user()->id,
            (int) $planId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new MealPlanResource($plan),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
