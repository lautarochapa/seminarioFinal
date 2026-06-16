<?php

namespace App\Http\Controllers\Api\V1\FamilyGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FamilyGroup\CreateFamilyGroupRequest;
use App\Http\Requests\Api\V1\FamilyGroup\UpdateFamilyGroupRequest;
use App\Http\Resources\Api\V1\FamilyGroup\FamilyGroupResource;
use App\Services\FamilyGroup\FamilyGroupService;
use Illuminate\Http\Request;

class FamilyGroupController extends Controller
{
    private $service;

    public function __construct(FamilyGroupService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $groups  = $this->service->list($request->user()->id);

        return response()->json([
            'data'     => FamilyGroupResource::collection($groups),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $group   = $this->service->show((int) $id, $request->user()->id);

        return response()->json([
            'data'     => new FamilyGroupResource($group),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(CreateFamilyGroupRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $group   = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new FamilyGroupResource($group),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateFamilyGroupRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $group   = $this->service->update(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new FamilyGroupResource($group),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $group   = $this->service->delete(
            (int) $id,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new FamilyGroupResource($group),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
