<?php

namespace App\Http\Controllers\Api\V1\Promotions;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Promotions\PromotionResource;
use App\Services\Promotions\PromotionService;
use Illuminate\Http\Request;

class BranchPromotionController extends Controller
{
    private $service;

    public function __construct(PromotionService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $branchId)
    {
        $traceId    = $request->attributes->get('trace_id');
        $promotions = $this->service->forBranch((int) $branchId, $request->query());

        return response()->json([
            'data'     => PromotionResource::collection($promotions),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
