<?php

namespace App\Http\Controllers\Api\V1\MealPlanItems;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MealPlanItems\StoreMealPlanItemRequest;
use App\Http\Requests\Api\V1\MealPlanItems\UpdateMealPlanItemRequest;
use App\Http\Resources\Api\V1\MealPlans\MealPlanItemResource;
use App\Services\MealPlanItems\MealPlanItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPlanItemController extends Controller
{
    private MealPlanItemService $service;

    public function __construct(MealPlanItemService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, int $id, int $planId): JsonResponse
    {
        $items = $this->service->list($request->user(), $id, $planId);
        return response()->json(['data' => MealPlanItemResource::collection($items)]);
    }

    public function store(StoreMealPlanItemRequest $request, int $id, int $planId): JsonResponse
    {
        $item = $this->service->create(
            $request->user(),
            $id,
            $planId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['data' => new MealPlanItemResource($item)], 201);
    }

    public function update(UpdateMealPlanItemRequest $request, int $id, int $planId, int $itemId): JsonResponse
    {
        $item = $this->service->update(
            $request->user(),
            $id,
            $planId,
            $itemId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['data' => new MealPlanItemResource($item)]);
    }

    public function destroy(Request $request, int $id, int $planId, int $itemId): JsonResponse
    {
        $this->service->delete(
            $request->user(),
            $id,
            $planId,
            $itemId,
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['message' => 'Item eliminado.']);
    }
}
