<?php

namespace App\Http\Controllers\Api\V1\FamilyGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FamilyGroup\UpdatePreferencesRequest;
use App\Http\Resources\Api\V1\FamilyGroup\FamilyGroupPreferenceResource;
use App\Services\FamilyGroup\FamilyGroupPreferenceService;
use Illuminate\Http\Request;

class FamilyGroupPreferenceController extends Controller
{
    private $service;

    public function __construct(FamilyGroupPreferenceService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $pref    = $this->service->show((int) $id, $request->user()->id);

        return response()->json([
            'data'     => new FamilyGroupPreferenceResource($pref),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function update(UpdatePreferencesRequest $request, $id)
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
            'data'     => new FamilyGroupPreferenceResource($pref),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
