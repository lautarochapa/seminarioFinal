<?php

namespace App\Http\Controllers\Api\V1\RecipeAvailability;

use App\Http\Controllers\Controller;
use App\Services\RecipeAvailability\RecipeAvailabilityService;
use Illuminate\Http\Request;

class RecipeAvailabilityController extends Controller
{
    private RecipeAvailabilityService $service;

    public function __construct(RecipeAvailabilityService $service)
    {
        $this->service = $service;
    }

    public function availability(Request $request, $recipeId)
    {
        $this->validate($request, [
            'family_group_id' => 'required|integer|min:1',
        ]);

        $traceId       = $request->attributes->get('trace_id');
        $familyGroupId = (int) $request->query('family_group_id');

        $result = $this->service->availability($request->user(), $recipeId, $familyGroupId);

        return response()->json(['data' => $result, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function missingIngredients(Request $request, $recipeId)
    {
        $this->validate($request, [
            'family_group_id' => 'required|integer|min:1',
        ]);

        $traceId       = $request->attributes->get('trace_id');
        $familyGroupId = (int) $request->query('family_group_id');

        $result = $this->service->missingIngredients($request->user(), $recipeId, $familyGroupId);

        return response()->json(['data' => $result, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }
}
