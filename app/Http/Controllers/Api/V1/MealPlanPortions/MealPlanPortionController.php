<?php

namespace App\Http\Controllers\Api\V1\MealPlanPortions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MealPlanPortions\StoreMealPlanPortionRequest;
use App\Http\Requests\Api\V1\MealPlanPortions\UpdateMealPlanPortionRequest;
use App\Http\Resources\Api\V1\MealPlanPortions\MealPlanPortionResource;
use App\Services\MealPlanPortions\MealPlanPortionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPlanPortionController extends Controller
{
    private MealPlanPortionService $service;

    public function __construct(MealPlanPortionService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, int $id, int $planId, int $itemId): JsonResponse
    {
        $portions = $this->service->list($request->user(), $id, $planId, $itemId);
        return response()->json(['data' => MealPlanPortionResource::collection($portions)]);
    }

    public function store(StoreMealPlanPortionRequest $request, int $id, int $planId, int $itemId): JsonResponse
    {
        $portion = $this->service->create(
            $request->user(),
            $id,
            $planId,
            $itemId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['data' => new MealPlanPortionResource($portion)], 201);
    }

    public function update(UpdateMealPlanPortionRequest $request, int $id, int $planId, int $itemId, int $portionId): JsonResponse
    {
        $portion = $this->service->update(
            $request->user(),
            $id,
            $planId,
            $itemId,
            $portionId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['data' => new MealPlanPortionResource($portion)]);
    }
}
