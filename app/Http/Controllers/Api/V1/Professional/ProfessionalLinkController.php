<?php

namespace App\Http\Controllers\Api\V1\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Professional\CreateLinkRequest;
use App\Http\Requests\Api\V1\Professional\UpdateLinkRequest;
use App\Http\Resources\Api\V1\Professional\ProfessionalLinkResource;
use App\Services\Professional\ProfessionalLinkService;
use Illuminate\Http\Request;

class ProfessionalLinkController extends Controller
{
    private $service;

    public function __construct(ProfessionalLinkService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $links   = $this->service->list($request->user()->id);

        return response()->json([
            'data'     => ProfessionalLinkResource::collection($links),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(CreateLinkRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $link    = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ProfessionalLinkResource($link),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateLinkRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $link    = $this->service->update(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ProfessionalLinkResource($link),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $link    = $this->service->revoke(
            (int) $id,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ProfessionalLinkResource($link),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
