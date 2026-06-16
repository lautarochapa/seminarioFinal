<?php

namespace App\Http\Controllers\Api\V1\RecipeCost;

use App\Http\Controllers\Controller;
use App\Services\RecipeCost\RecipeCostService;
use Illuminate\Http\Request;

class RecipeCostController extends Controller
{
    private RecipeCostService $service;

    public function __construct(RecipeCostService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request, $recipeId)
    {
        $this->validate($request, [
            'family_group_id' => 'sometimes|integer|min:1',
        ]);

        $traceId       = $request->attributes->get('trace_id');
        $familyGroupId = $request->query('family_group_id') ? (int) $request->query('family_group_id') : null;

        $result = $this->service->show($request->user(), $recipeId, $familyGroupId);

        return response()->json(['data' => $result, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function recalculate(Request $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');

        $result = $this->service->recalculate(
            $request->user(),
            $recipeId,
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json(['data' => $result, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }
}
