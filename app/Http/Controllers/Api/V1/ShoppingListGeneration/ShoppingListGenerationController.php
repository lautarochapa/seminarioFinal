<?php

namespace App\Http\Controllers\Api\V1\ShoppingListGeneration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShoppingListGeneration\GenerateFromHistoryRequest;
use App\Http\Requests\Api\V1\ShoppingListGeneration\GenerateFromMealPlanRequest;
use App\Http\Resources\Api\V1\ShoppingLists\ShoppingListResource;
use App\Services\ShoppingListGeneration\ShoppingListGenerationService;
use Illuminate\Http\JsonResponse;

class ShoppingListGenerationController extends Controller
{
    private $service;

    public function __construct(ShoppingListGenerationService $service)
    {
        $this->service = $service;
    }

    public function fromMealPlan(GenerateFromMealPlanRequest $request, int $id): JsonResponse
    {
        $result = $this->service->fromMealPlan(
            $request->user(),
            $id,
            (int) $request->validated()['meal_plan_id'],
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => new ShoppingListResource($result['list']),
            'trace_id' => $request->attributes->get('trace_id'),
        ], $result['created'] ? 201 : 200);
    }

    public function fromHistory(GenerateFromHistoryRequest $request, int $id): JsonResponse
    {
        $result = $this->service->fromHistory(
            $request->user(),
            $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => new ShoppingListResource($result['list']),
            'trace_id' => $request->attributes->get('trace_id'),
        ], $result['created'] ? 201 : 200);
    }
}
