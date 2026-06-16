<?php

namespace App\Http\Controllers\Api\V1\StockAlerts;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\HouseholdStock\StockItemResource;
use App\Http\Resources\Api\V1\StockAlerts\StockAlertResource;
use App\Services\StockAlerts\StockAlertService;
use Illuminate\Http\Request;

class StockAlertController extends Controller
{
    private $service;

    public function __construct(StockAlertService $service)
    {
        $this->service = $service;
    }

    public function expiring(Request $request, $id)
    {
        return $this->paginated($request, $this->service->expiring((int) $id, $request->user()->id, $request->query()), StockItemResource::class);
    }

    public function lowStock(Request $request, $id)
    {
        return $this->paginated($request, $this->service->lowStock((int) $id, $request->user()->id, $request->query()), StockItemResource::class);
    }

    public function index(Request $request, $id)
    {
        return $this->paginated($request, $this->service->alerts((int) $id, $request->user()->id, $request->query()), StockAlertResource::class);
    }

    public function read(Request $request, $id, $alertId)
    {
        $traceId = $request->attributes->get('trace_id');
        $alert = $this->service->markRead((int) $id, (int) $alertId, $request->user()->id, $request->ip(), $request->userAgent());

        return response()->json([
            'data' => new StockAlertResource($alert),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    private function paginated(Request $request, $paginator, $resourceClass)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => $resourceClass::collection($paginator),
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
