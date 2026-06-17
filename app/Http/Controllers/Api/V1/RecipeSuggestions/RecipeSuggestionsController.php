<?php

namespace App\Http\Controllers\Api\V1\RecipeSuggestions;

use App\Http\Controllers\Controller;
use App\Services\RecipeSuggestions\RecipeSuggestionsService;
use Illuminate\Http\Request;

class RecipeSuggestionsController extends Controller
{
    private RecipeSuggestionsService $service;

    public function __construct(RecipeSuggestionsService $service)
    {
        $this->service = $service;
    }

    public function suggestions(Request $request)
    {
        $this->validate($request, [
            'family_group_id' => 'sometimes|integer|min:1',
            'page'            => 'sometimes|integer|min:1',
            'per_page'        => 'sometimes|integer|min:1|max:100',
        ]);

        $traceId       = $request->attributes->get('trace_id');
        $familyGroupId = $request->query('family_group_id') ? (int) $request->query('family_group_id') : null;
        $result        = $this->service->suggestions(
            $request->user(),
            $familyGroupId,
            (int) $request->query('page', 1),
            min((int) $request->query('per_page', 20), 100)
        );

        return response()->json(array_merge($result, ['trace_id' => $traceId]))->header('X-Trace-Id', $traceId);
    }

    public function available(Request $request, $groupId)
    {
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->available(
            $request->user(),
            (int) $groupId,
            (int) $request->query('page', 1),
            min((int) $request->query('per_page', 20), 100)
        );

        return response()->json(array_merge($result, ['trace_id' => $traceId]))->header('X-Trace-Id', $traceId);
    }

    public function almostAvailable(Request $request, $groupId)
    {
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->almostAvailable(
            $request->user(),
            (int) $groupId,
            (int) $request->query('page', 1),
            min((int) $request->query('per_page', 20), 100)
        );

        return response()->json(array_merge($result, ['trace_id' => $traceId]))->header('X-Trace-Id', $traceId);
    }

    public function byExpiringStock(Request $request, $groupId)
    {
        $this->validate($request, ['days' => 'sometimes|integer|min:1|max:30']);
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->byExpiringStock(
            $request->user(),
            (int) $groupId,
            (int) $request->query('days', 7),
            (int) $request->query('page', 1),
            min((int) $request->query('per_page', 20), 100)
        );

        return response()->json(array_merge($result, ['trace_id' => $traceId]))->header('X-Trace-Id', $traceId);
    }

    public function byBudget(Request $request, $groupId)
    {
        $this->validate($request, ['max_cost' => 'sometimes|numeric|min:0']);
        $traceId = $request->attributes->get('trace_id');
        $maxCost = $request->query('max_cost') !== null ? (float) $request->query('max_cost') : null;
        $result  = $this->service->byBudget(
            $request->user(),
            (int) $groupId,
            $maxCost,
            (int) $request->query('page', 1),
            min((int) $request->query('per_page', 20), 100)
        );

        return response()->json(array_merge($result, ['trace_id' => $traceId]))->header('X-Trace-Id', $traceId);
    }

    public function byObjectives(Request $request, $groupId)
    {
        $traceId = $request->attributes->get('trace_id');
        $result  = $this->service->byObjectives(
            $request->user(),
            (int) $groupId,
            (int) $request->query('page', 1),
            min((int) $request->query('per_page', 20), 100)
        );

        return response()->json(array_merge($result, ['trace_id' => $traceId]))->header('X-Trace-Id', $traceId);
    }
}
