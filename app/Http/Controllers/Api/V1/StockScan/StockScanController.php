<?php

namespace App\Http\Controllers\Api\V1\StockScan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockScan\StockScanRequest;
use App\Http\Resources\Api\V1\StockScan\StockScanResource;
use App\Services\StockScan\StockScanService;

class StockScanController extends Controller
{
    private $service;

    public function __construct(StockScanService $service)
    {
        $this->service = $service;
    }

    public function store(StockScanRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        list($item, $status) = $this->service->scan(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => new StockScanResource($item),
            'trace_id' => $traceId,
        ], $status)->header('X-Trace-Id', $traceId);
    }
}
