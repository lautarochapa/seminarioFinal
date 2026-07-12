<?php

namespace App\Http\Controllers\Api\V1\FamilyGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FamilyGroup\AddMemberRequest;
use App\Http\Requests\Api\V1\FamilyGroup\UpdateMemberRequest;
use App\Http\Resources\Api\V1\FamilyGroup\FamilyGroupMemberResource;
use App\Services\FamilyGroup\FamilyGroupMemberService;
use Illuminate\Http\Request;

class FamilyGroupMemberController extends Controller
{
    private $service;

    public function __construct(FamilyGroupMemberService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $members = $this->service->list((int) $id, $request->user()->id);

        return response()->json([
            'data'     => FamilyGroupMemberResource::collection($members),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(AddMemberRequest $request, $id)
    {
        $traceId    = $request->attributes->get('trace_id');
        $membership = $this->service->add(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new FamilyGroupMemberResource($membership),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateMemberRequest $request, $id, $memberId)
    {
        $traceId    = $request->attributes->get('trace_id');
        $membership = $this->service->update(
            (int) $id,
            (int) $memberId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new FamilyGroupMemberResource($membership),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $memberId)
    {
        $traceId = $request->attributes->get('trace_id');

        $this->service->remove(
            (int) $id,
            (int) $memberId,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->noContent()->header('X-Trace-Id', $traceId);
    }
}
