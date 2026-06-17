<?php

namespace App\Http\Controllers\Api\V1\RecipeIngredients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeIngredients\RecipeIngredientRequest;
use App\Http\Resources\Api\V1\RecipeIngredients\RecipeIngredientResource;
use App\Services\RecipeIngredients\RecipeIngredientService;
use Illuminate\Http\Request;

class RecipeIngredientController extends Controller
{
    private $service;

    public function __construct(RecipeIngredientService $service)
    {
        $this->service = $service;
    }

    public function store(RecipeIngredientRequest $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $row     = $this->service->add(
            $request->user(),
            (int) $recipeId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeIngredientResource($row),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(RecipeIngredientRequest $request, $recipeId, $ingredientId)
    {
        $traceId = $request->attributes->get('trace_id');
        $row     = $this->service->update(
            $request->user(),
            (int) $recipeId,
            (int) $ingredientId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeIngredientResource($row),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $recipeId, $ingredientId)
    {
        $traceId = $request->attributes->get('trace_id');
        $row     = $this->service->remove(
            $request->user(),
            (int) $recipeId,
            (int) $ingredientId,
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeIngredientResource($row),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
