<?php

namespace App\Http\Controllers\Api\V1\Supermarkets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Supermarkets\StoreSupermarketChainRequest;
use App\Http\Requests\Api\V1\Supermarkets\UpdateSupermarketChainRequest;
use App\Http\Resources\Api\V1\Supermarkets\SupermarketChainResource;
use App\Services\Supermarkets\SupermarketChainService;
use Illuminate\Http\Request;

class AdminSupermarketChainController extends Controller
{
    private $service;

    public function __construct(SupermarketChainService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->query());

        return response()->json([
            'data'  => SupermarketChainResource::collection($paginator),
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

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $chain   = $this->service->show((int) $id);

        return response()->json([
            'data'     => new SupermarketChainResource($chain),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StoreSupermarketChainRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $chain   = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new SupermarketChainResource($chain),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateSupermarketChainRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $chain   = $this->service->update(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new SupermarketChainResource($chain),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $chain   = $this->service->delete(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new SupermarketChainResource($chain),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $chain   = $this->service->restore(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new SupermarketChainResource($chain),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
