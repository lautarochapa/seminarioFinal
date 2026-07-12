<?php

namespace App\Http\Controllers\Api\V1\HealthPreferences;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HealthPreferences\UserHealthPreferenceRequest;
use App\Http\Resources\Api\V1\HealthPreferences\UserHealthPreferenceResource;
use App\Services\HealthPreferences\UserHealthPreferenceService;
use App\Support\HealthPreferenceTypes;
use Illuminate\Http\Request;

class UserHealthPreferenceController extends Controller
{
    private $service;

    public function __construct(UserHealthPreferenceService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $healthPreferenceType)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => UserHealthPreferenceResource::collection($this->service->list(HealthPreferenceTypes::get($healthPreferenceType), $request->user()->id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(UserHealthPreferenceRequest $request, $healthPreferenceType)
    {
        $traceId = $request->attributes->get('trace_id');
        $relation = $this->service->create(HealthPreferenceTypes::get($healthPreferenceType), $request->user()->id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new UserHealthPreferenceResource($relation),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $healthPreferenceType, $id)
    {
        $this->service->delete(HealthPreferenceTypes::get($healthPreferenceType), $request->user()->id, (int) $id, $request->ip(), $request->userAgent() ?? '');

        return response()->noContent();
    }
}
