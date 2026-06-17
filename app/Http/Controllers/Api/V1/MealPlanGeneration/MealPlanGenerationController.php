<?php

namespace App\Http\Controllers\Api\V1\MealPlanGeneration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MealPlanGeneration\GenerateMealPlanRequest;
use App\Http\Resources\Api\V1\MealPlans\MealPlanResource;
use App\Services\MealPlanGeneration\MealPlanGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPlanGenerationController extends Controller
{
    private MealPlanGenerationService $service;

    public function __construct(MealPlanGenerationService $service)
    {
        $this->service = $service;
    }

    public function generate(GenerateMealPlanRequest $request, int $id): JsonResponse
    {
        $plan = $this->service->generate(
            $request->user(),
            $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['data' => new MealPlanResource($plan)], 201);
    }

    public function approve(Request $request, int $id, int $planId): JsonResponse
    {
        $plan = $this->service->approve(
            $request->user(),
            $id,
            $planId,
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['data' => new MealPlanResource($plan)]);
    }

    public function regenerate(Request $request, int $id, int $planId): JsonResponse
    {
        $plan = $this->service->regenerate(
            $request->user(),
            $id,
            $planId,
            $request->only(['period_type', 'start_date', 'end_date']),
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['data' => new MealPlanResource($plan)]);
    }
}
