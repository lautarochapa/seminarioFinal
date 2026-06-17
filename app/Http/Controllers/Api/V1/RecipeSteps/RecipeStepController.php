<?php

namespace App\Http\Controllers\Api\V1\RecipeSteps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeSteps\RecipeStepRequest;
use App\Http\Resources\Api\V1\RecipeSteps\RecipeStepResource;
use App\Services\RecipeSteps\RecipeStepService;
use Illuminate\Http\Request;

class RecipeStepController extends Controller
{
    private $service;

    public function __construct(RecipeStepService $service)
    {
        $this->service = $service;
    }

    public function store(RecipeStepRequest $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $step    = $this->service->add(
            $request->user(),
            (int) $recipeId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeStepResource($step),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(RecipeStepRequest $request, $recipeId, $stepId)
    {
        $traceId = $request->attributes->get('trace_id');
        $step    = $this->service->update(
            $request->user(),
            (int) $recipeId,
            (int) $stepId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeStepResource($step),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $recipeId, $stepId)
    {
        $traceId = $request->attributes->get('trace_id');
        $step    = $this->service->remove(
            $request->user(),
            (int) $recipeId,
            (int) $stepId,
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeStepResource($step),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
