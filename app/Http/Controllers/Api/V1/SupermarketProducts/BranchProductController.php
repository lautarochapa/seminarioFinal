<?php

namespace App\Http\Controllers\Api\V1\SupermarketProducts;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SupermarketProducts\SupermarketProductResource;
use App\Services\SupermarketProducts\SupermarketProductService;
use Illuminate\Http\Request;

class BranchProductController extends Controller
{
    private $service;

    public function __construct(SupermarketProductService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $branchId)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->branchProducts((int) $branchId, $request->query());

        $collection = $paginator->map(function ($sp) {
            $sp->current_price = $sp->prices->first() ?? null;
            return $sp;
        });

        return response()->json([
            'data'  => SupermarketProductResource::collection($collection),
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
}
