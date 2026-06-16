<?php

namespace App\Http\Controllers\Api\V1\HouseholdStock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HouseholdStock\StoreStockItemRequest;
use App\Http\Requests\Api\V1\HouseholdStock\UpdateStockItemRequest;
use App\Http\Resources\Api\V1\HouseholdStock\StockItemResource;
use App\Services\HouseholdStock\HouseholdStockService;
use Illuminate\Http\Request;

class HouseholdStockController extends Controller
{
    private $service;

    public function __construct(HouseholdStockService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id)
    {
        return $this->paginated($request, $this->service->list((int) $id, $request->user()->id, $request->query()));
    }

    public function summary(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => $this->service->summary((int) $id, $request->user()->id),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function value(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => $this->service->value((int) $id, $request->user()->id),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StoreStockItemRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        list($item, $status) = $this->service->create(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => new StockItemResource($item),
            'trace_id' => $traceId,
        ], $status)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateStockItemRequest $request, $id, $stockItemId)
    {
        $traceId = $request->attributes->get('trace_id');
        $item = $this->service->update(
            (int) $id,
            (int) $stockItemId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => new StockItemResource($item),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $stockItemId)
    {
        $traceId = $request->attributes->get('trace_id');
        $item = $this->service->delete(
            (int) $id,
            (int) $stockItemId,
            $request->user()->id,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => new StockItemResource($item),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    private function paginated(Request $request, $paginator)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => StockItemResource::collection($paginator),
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
}
