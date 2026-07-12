<?php

namespace App\Http\Controllers\Api\V1\MealPlanItemStatus;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MealPlanItemStatus\MealPlanItemStatusRequest;
use App\Http\Resources\Api\V1\MealPlans\MealPlanItemResource;
use App\Services\MealPlanItemStatus\MealPlanItemStatusService;
use Illuminate\Http\JsonResponse;

class MealPlanItemStatusController extends Controller
{
    private $service;

    public function __construct(MealPlanItemStatusService $service)
    {
        $this->service = $service;
    }

    public function markCooked(MealPlanItemStatusRequest $request, int $id, int $planId, int $itemId): JsonResponse
    {
        $item = $this->service->markCooked(
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

    public function skip(MealPlanItemStatusRequest $request, int $id, int $planId, int $itemId): JsonResponse
    {
        $item = $this->service->skip(
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
}
