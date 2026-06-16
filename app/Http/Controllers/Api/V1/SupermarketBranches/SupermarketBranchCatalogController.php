<?php

namespace App\Http\Controllers\Api\V1\SupermarketBranches;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SupermarketBranches\NearbyRequest;
use App\Http\Resources\Api\V1\SupermarketBranches\SupermarketBranchResource;
use App\Services\Supermarkets\SupermarketBranchService;
use Illuminate\Http\Request;

class SupermarketBranchCatalogController extends Controller
{
    private $service;

    public function __construct(SupermarketBranchService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId  = $request->attributes->get('trace_id');
        $branches = $this->service->catalog($request->query());

        return response()->json([
            'data'     => SupermarketBranchResource::collection($branches),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $branch  = $this->service->catalogShow((int) $id);

        return response()->json([
            'data'     => new SupermarketBranchResource($branch),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function nearby(NearbyRequest $request)
    {
        $traceId  = $request->attributes->get('trace_id');
        $lat      = (float) $request->query('lat');
        $lng      = (float) $request->query('lng');
        $radius   = (float) $request->query('radius');

        $branches = $this->service->nearby($lat, $lng, $radius);

        return response()->json([
            'data'     => SupermarketBranchResource::collection($branches),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
