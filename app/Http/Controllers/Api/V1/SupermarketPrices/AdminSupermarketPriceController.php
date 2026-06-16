<?php

namespace App\Http\Controllers\Api\V1\SupermarketPrices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SupermarketPrices\StorePriceRequest;
use App\Http\Resources\Api\V1\SupermarketPrices\PriceResource;
use App\Services\SupermarketProducts\SupermarketProductService;
use Illuminate\Http\Request;

class AdminSupermarketPriceController extends Controller
{
    private $service;

    public function __construct(SupermarketProductService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->priceHistory((int) $id, $request->query());

        return response()->json([
            'data'  => PriceResource::collection($paginator),
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

    public function store(StorePriceRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $price   = $this->service->addPrice(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PriceResource($price),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }
}
