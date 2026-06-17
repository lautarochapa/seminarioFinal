<?php

namespace App\Http\Controllers\Api\V1\RecipeSubstitutions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeSubstitutions\RecipeSubstitutionsRequest;
use App\Services\RecipeSubstitutions\RecipeSubstitutionsService;

class RecipeSubstitutionsController extends Controller
{
    private RecipeSubstitutionsService $service;

    public function __construct(RecipeSubstitutionsService $service)
    {
        $this->service = $service;
    }

    public function __invoke(RecipeSubstitutionsRequest $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->substitutions(
            $request->user(),
            (int) $recipeId,
            $request->validated()
        );

        return response()->json(array_merge(['data' => $data], ['trace_id' => $traceId]))
            ->header('X-Trace-Id', $traceId);
    }
}
