<?php

namespace App\Http\Controllers\Api\V1\Nutrients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Nutrients\StoreIngredientNutrientRequest;
use App\Http\Requests\Api\V1\Nutrients\UpdateIngredientNutrientRequest;
use App\Http\Resources\Api\V1\Nutrients\IngredientNutrientResource;
use App\Services\Nutrients\IngredientNutrientService;
use Illuminate\Http\Request;

class IngredientNutrientController extends Controller
{
    private $service;

    public function __construct(IngredientNutrientService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $ingredientId)
    {
        $traceId = $request->attributes->get('trace_id');
        $items   = $this->service->list((int) $ingredientId);

        return response()->json([
            'data'     => IngredientNutrientResource::collection($items),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StoreIngredientNutrientRequest $request, $ingredientId)
    {
        $traceId  = $request->attributes->get('trace_id');
        $relation = $this->service->attach(
            $request->user()->id,
            (int) $ingredientId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new IngredientNutrientResource($relation),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateIngredientNutrientRequest $request, $ingredientId, $nutrientId)
    {
        $traceId  = $request->attributes->get('trace_id');
        $relation = $this->service->update(
            $request->user()->id,
            (int) $ingredientId,
            (int) $nutrientId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new IngredientNutrientResource($relation),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
