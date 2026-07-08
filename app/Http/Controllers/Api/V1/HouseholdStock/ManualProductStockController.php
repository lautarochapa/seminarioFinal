<?php

namespace App\Http\Controllers\Api\V1\HouseholdStock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HouseholdStock\ManualProductStockRequest;
use App\Http\Resources\Api\V1\HouseholdStock\StockItemResource;
use App\Http\Resources\Api\V1\Products\ProductResource;
use App\Services\ManualProductStock\ManualProductStockService;

class ManualProductStockController extends Controller
{
    private $service;

    public function __construct(ManualProductStockService $service)
    {
        $this->service = $service;
    }

    public function store(ManualProductStockRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $result = $this->service->create(
            (int) $id,
            $request->user(),
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => [
                'product' => new ProductResource($result['product']),
                'stock_item' => new StockItemResource($result['stock_item']),
                'review_status' => $result['review_status'],
                'matched_existing_product' => $result['matched_existing_product'],
                'reused_pending_product' => $result['reused_pending_product'],
            ],
            'message' => $result['message'],
            'trace_id' => $traceId,
        ], $result['status'])->header('X-Trace-Id', $traceId);
    }
}
