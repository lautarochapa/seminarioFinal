<?php

namespace App\Http\Controllers\Api\V1\MealPlans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MealPlans\StoreMealPlanRequest;
use App\Http\Requests\Api\V1\MealPlans\UpdateMealPlanRequest;
use App\Http\Resources\Api\V1\MealPlans\MealPlanResource;
use App\Services\MealPlans\MealPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPlanController extends Controller
{
    private MealPlanService $service;

    public function __construct(MealPlanService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, int $id): JsonResponse
    {
        $paginator = $this->service->list($request->user(), $id, $request->all());
        return response()->json([
            'data'  => MealPlanResource::collection($paginator->items()),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, int $id, int $planId): JsonResponse
    {
        $plan = $this->service->show($request->user(), $id, $planId);
        return response()->json(['data' => new MealPlanResource($plan)]);
    }

    public function store(StoreMealPlanRequest $request, int $id): JsonResponse
    {
        $plan = $this->service->create(
            $request->user(),
            $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['data' => new MealPlanResource($plan)], 201);
    }

    public function update(UpdateMealPlanRequest $request, int $id, int $planId): JsonResponse
    {
        $plan = $this->service->update(
            $request->user(),
            $id,
            $planId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['data' => new MealPlanResource($plan)]);
    }

    public function destroy(Request $request, int $id, int $planId): JsonResponse
    {
        $this->service->destroy(
            $request->user(),
            $id,
            $planId,
            $request->ip(),
            $request->userAgent() ?? ''
        );
        return response()->json(['message' => 'Plan eliminado.']);
    }
}
