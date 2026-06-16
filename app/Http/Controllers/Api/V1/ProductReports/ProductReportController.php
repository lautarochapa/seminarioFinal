<?php

namespace App\Http\Controllers\Api\V1\ProductReports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductReports\CreateProductReportRequest;
use App\Http\Resources\Api\V1\ProductReports\ProductReportResource;
use App\Services\ProductReports\ProductReportService;

class ProductReportController extends Controller
{
    private $service;

    public function __construct(ProductReportService $service)
    {
        $this->service = $service;
    }

    public function store(CreateProductReportRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $validated = $request->validated();

        $report = $this->service->create(
            $request->user()->id,
            (int) $id,
            $validated,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ProductReportResource($report),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }
}
