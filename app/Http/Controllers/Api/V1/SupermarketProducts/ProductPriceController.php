<?php

namespace App\Http\Controllers\Api\V1\SupermarketProducts;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SupermarketProducts\SupermarketProductResource;
use App\Services\SupermarketProducts\SupermarketProductService;
use Illuminate\Http\Request;

class ProductPriceController extends Controller
{
    private $service;

    public function __construct(SupermarketProductService $service)
    {
        $this->service = $service;
    }

    public function supermarketPrices(Request $request, $productId)
    {
        $traceId = $request->attributes->get('trace_id');
        $prices  = $this->service->supermarketPrices((int) $productId);

        return response()->json([
            'data'     => SupermarketProductResource::collection($prices),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function bestPrice(Request $request, $productId)
    {
        $traceId = $request->attributes->get('trace_id');
        $best    = $this->service->bestPrice((int) $productId);

        return response()->json([
            'data'     => new SupermarketProductResource($best),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
