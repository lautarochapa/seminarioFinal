<?php

namespace App\Http\Controllers\Api\V1\BodyMeasurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BodyMeasurement\CreateBodyMeasurementRequest;
use App\Http\Requests\Api\V1\BodyMeasurement\UpdateBodyMeasurementRequest;
use App\Http\Resources\Api\V1\BodyMeasurement\BodyMeasurementResource;
use App\Services\BodyMeasurement\BodyMeasurementService;
use Illuminate\Http\Request;

class BodyMeasurementController extends Controller
{
    private $service;

    public function __construct(BodyMeasurementService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $filters   = $request->only(['date_from', 'date_to', 'page', 'per_page']);
        $paginator = $this->service->list($request->user()->id, $filters);

        return response()->json([
            'data'     => BodyMeasurementResource::collection($paginator),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links'    => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(CreateBodyMeasurementRequest $request)
    {
        $traceId     = $request->attributes->get('trace_id');
        $measurement = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new BodyMeasurementResource($measurement),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateBodyMeasurementRequest $request, $id)
    {
        $traceId     = $request->attributes->get('trace_id');
        $measurement = $this->service->update(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new BodyMeasurementResource($measurement),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId     = $request->attributes->get('trace_id');
        $measurement = $this->service->delete(
            (int) $id,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new BodyMeasurementResource($measurement),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
