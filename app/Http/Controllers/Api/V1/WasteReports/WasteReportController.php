<?php

namespace App\Http\Controllers\Api\V1\WasteReports;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WasteReports\WasteReportResource;
use App\Services\WasteReports\WasteReportService;
use Illuminate\Http\Request;

class WasteReportController extends Controller
{
    private $service;

    public function __construct(WasteReportService $service)
    {
        $this->service = $service;
    }

    public function show(Request $request, $id)
    {
        $result = $this->service->report((int) $id, $request->user()->id, $request->query());
        $paginator = $result['paginator'];
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => WasteReportResource::collection($paginator),
            'totals' => $result['totals'],
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
