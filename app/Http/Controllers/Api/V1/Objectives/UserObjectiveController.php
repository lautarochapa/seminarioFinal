<?php

namespace App\Http\Controllers\Api\V1\Objectives;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Objectives\StoreUserObjectiveRequest;
use App\Http\Requests\Api\V1\Objectives\UpdateUserObjectiveRequest;
use App\Http\Resources\Api\V1\Objectives\UserObjectiveResource;
use App\Services\Objectives\UserObjectiveService;
use Illuminate\Http\Request;

class UserObjectiveController extends Controller
{
    private $service;

    public function __construct(UserObjectiveService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => UserObjectiveResource::collection($this->service->list($request->user()->id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StoreUserObjectiveRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $assignment = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => new UserObjectiveResource($assignment),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateUserObjectiveRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $assignment = $this->service->update(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => new UserObjectiveResource($assignment),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $this->service->delete(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->noContent();
    }
}
