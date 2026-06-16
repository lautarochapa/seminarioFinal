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

    public function index(Request $request, $type)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => UserHealthPreferenceResource::collection($this->service->list(HealthPreferenceTypes::get($type), $request->user()->id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(UserHealthPreferenceRequest $request, $type)
    {
        $traceId = $request->attributes->get('trace_id');
        $relation = $this->service->create(HealthPreferenceTypes::get($type), $request->user()->id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new UserHealthPreferenceResource($relation),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $type, $id)
    {
        $this->service->delete(HealthPreferenceTypes::get($type), $request->user()->id, (int) $id, $request->ip(), $request->userAgent() ?? '');

        return response()->noContent();
    }
}
