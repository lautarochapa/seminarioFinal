<?php

namespace App\Http\Controllers\Api\V1\RecipeNutrition;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RecipeNutrition\RecipeNutritionResource;
use App\Services\RecipeNutrition\RecipeNutritionService;
use Illuminate\Http\Request;

class RecipeNutritionController extends Controller
{
    private RecipeNutritionService $service;

    public function __construct(RecipeNutritionService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->show($request->user(), $recipeId);

        $nutrition = $result['nutrition'];
        $recipe    = $result['recipe'];

        return response()->json([
            'data' => [
                'recipe_id' => $recipe->id,
                'servings'  => $recipe->servings,
                'nutrition' => $nutrition ? new RecipeNutritionResource($nutrition) : null,
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function recalculate(Request $request, $recipeId)
    {
        $traceId   = $request->attributes->get('trace_id');
        $nutrition = $this->service->recalculate(
            $request->user(),
            $recipeId,
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeNutritionResource($nutrition),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
