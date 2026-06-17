<?php

namespace App\Http\Controllers\Api\V1\MealPlanIncompatibilities;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MealPlanIncompatibilities\MealPlanIncompatibilityResource;
use App\Services\MealPlanIncompatibilities\MealPlanIncompatibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPlanIncompatibilityController extends Controller
{
    private MealPlanIncompatibilityService $service;

    public function __construct(MealPlanIncompatibilityService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, int $id, int $planId): JsonResponse
    {
        $items = $this->service->list($request->user(), $id, $planId);

        return response()->json([
            'data'     => MealPlanIncompatibilityResource::collection($items),
            'trace_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    public function check(Request $request, int $id, int $planId): JsonResponse
    {
        $items = $this->service->check(
            $request->user(),
            $id,
            $planId,
            $request->ip() ?? '',
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => MealPlanIncompatibilityResource::collection($items),
            'trace_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }
}
