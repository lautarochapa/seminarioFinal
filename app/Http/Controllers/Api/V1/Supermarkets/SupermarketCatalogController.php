<?php

namespace App\Http\Controllers\Api\V1\Supermarkets;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Supermarkets\SupermarketChainResource;
use App\Services\Supermarkets\SupermarketChainService;
use Illuminate\Http\Request;

class SupermarketCatalogController extends Controller
{
    private $service;

    public function __construct(SupermarketChainService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $chains  = $this->service->catalog();

        return response()->json([
            'data'     => SupermarketChainResource::collection($chains),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $chain   = $this->service->catalogShow((int) $id);

        return response()->json([
            'data'     => new SupermarketChainResource($chain),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
