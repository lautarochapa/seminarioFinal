<?php

namespace App\Http\Controllers\Api\V1\StockLocations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockLocations\StoreStockLocationRequest;
use App\Http\Requests\Api\V1\StockLocations\UpdateStockLocationRequest;
use App\Http\Resources\Api\V1\StockLocations\StockLocationResource;
use App\Services\StockLocations\StockLocationService;
use Illuminate\Http\Request;

class StockLocationController extends Controller
{
    private $service;

    public function __construct(StockLocationService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id)
    {
        $paginator = $this->service->list((int) $id, $request->user()->id, $request->query());

        return $this->paginated($request, $paginator);
    }

    public function store(StoreStockLocationRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $location = $this->service->create(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => new StockLocationResource($location),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateStockLocationRequest $request, $id, $locationId)
    {
        $traceId = $request->attributes->get('trace_id');
        $location = $this->service->update(
            (int) $id,
            (int) $locationId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => new StockLocationResource($location),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $locationId)
    {
        $traceId = $request->attributes->get('trace_id');
        $location = $this->service->delete(
            (int) $id,
            (int) $locationId,
            $request->user()->id,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => new StockLocationResource($location),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    private function paginated(Request $request, $paginator)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => StockLocationResource::collection($paginator),
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
