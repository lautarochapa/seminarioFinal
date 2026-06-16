<?php

namespace App\Http\Controllers\Api\V1\Cities;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cities\StoreCityRequest;
use App\Http\Requests\Api\V1\Cities\UpdateCityRequest;
use App\Http\Resources\Api\V1\Cities\CityResource;
use App\Services\Cities\CityService;
use Illuminate\Http\Request;

class AdminCityController extends Controller
{
    private $service;

    public function __construct(CityService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->query());

        return response()->json([
            'data'  => CityResource::collection($paginator),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StoreCityRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $city    = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new CityResource($city),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateCityRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $city    = $this->service->update(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new CityResource($city),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $city    = $this->service->deactivate(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new CityResource($city),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $city    = $this->service->restore(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new CityResource($city),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
