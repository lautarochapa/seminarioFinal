<?php

namespace App\Http\Controllers\Api\V1\ProductReports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductReports\ResolveProductReportRequest;
use App\Http\Resources\Api\V1\ProductReports\ProductReportResource;
use App\Services\ProductReports\ProductReportService;
use Illuminate\Http\Request;

class AdminProductReportController extends Controller
{
    private $service;

    public function __construct(ProductReportService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->query());

        return response()->json([
            'data'  => ProductReportResource::collection($paginator),
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

    public function resolve(ResolveProductReportRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $report  = $this->service->resolve(
            $request->user()->id,
            (int) $id,
            $request->validated()['status'],
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ProductReportResource($report),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
