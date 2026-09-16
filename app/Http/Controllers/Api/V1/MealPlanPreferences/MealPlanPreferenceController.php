<?php

namespace App\Http\Controllers\Api\V1\MealPlanPreferences;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MealPlanPreferences\UpdateMealPlanPreferencesRequest;
use App\Http\Resources\Api\V1\MealPlanPreferences\MealPlanPreferenceResource;
use App\Services\MealPlanPreferences\MealPlanPreferenceService;
use Illuminate\Http\Request;

class MealPlanPreferenceController extends Controller
{
    private $service;

    public function __construct(MealPlanPreferenceService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $pref    = $this->service->show((int) $id, $request->user()->id);

        return response()->json([
            'data'     => new MealPlanPreferenceResource($pref),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateMealPlanPreferencesRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $pref    = $this->service->update(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new MealPlanPreferenceResource($pref),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
