<?php

namespace App\Http\Controllers\Api\V1\FamilyGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FamilyGroup\CreateInvitationRequest;
use App\Http\Resources\Api\V1\FamilyGroup\FamilyGroupInvitationResource;
use App\Services\FamilyGroup\FamilyGroupInvitationService;
use Illuminate\Http\Request;

class FamilyGroupInvitationController extends Controller
{
    private $service;

    public function __construct(FamilyGroupInvitationService $service)
    {
        $this->service = $service;
    }

    public function store(CreateInvitationRequest $request, $id)
    {
        $traceId    = $request->attributes->get('trace_id');
        $invitation = $this->service->create(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new FamilyGroupInvitationResource($invitation),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function accept(Request $request, $invitationId)
    {
        $traceId    = $request->attributes->get('trace_id');
        $invitation = $this->service->accept(
            (int) $invitationId,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new FamilyGroupInvitationResource($invitation),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
