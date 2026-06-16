<?php

namespace App\Http\Controllers\Api\V1\StockMovements;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockMovements\AdjustStockRequest;
use App\Http\Requests\Api\V1\StockMovements\StockDecreaseRequest;
use App\Http\Resources\Api\V1\HouseholdStock\StockItemResource;
use App\Http\Resources\Api\V1\StockMovements\StockMovementResource;
use App\Services\StockMovements\StockMovementService;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    private $service;

    public function __construct(StockMovementService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id)
    {
        $paginator = $this->service->list((int) $id, $request->user()->id, $request->query());
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => StockMovementResource::collection($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function adjust(AdjustStockRequest $request, $id, $stockItemId)
    {
        return $this->stockResponse($request, $this->service->adjust(
            (int) $id,
            (int) $stockItemId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        ));
    }

    public function consume(StockDecreaseRequest $request, $id, $stockItemId)
    {
        return $this->stockResponse($request, $this->service->consume(
            (int) $id,
            (int) $stockItemId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        ));
    }

    public function discard(StockDecreaseRequest $request, $id, $stockItemId)
    {
        return $this->stockResponse($request, $this->service->discard(
            (int) $id,
            (int) $stockItemId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        ));
    }

    private function stockResponse(Request $request, $item)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => new StockItemResource($item),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
