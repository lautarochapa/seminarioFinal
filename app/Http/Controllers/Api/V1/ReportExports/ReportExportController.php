<?php

namespace App\Http\Controllers\Api\V1\ReportExports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportExports\CreateReportExportRequest;
use App\Services\ReportExports\ReportExportService;
use Illuminate\Http\Request;

class ReportExportController extends Controller
{
    private $service;

    public function __construct(ReportExportService $service)
    {
        $this->service = $service;
    }

    public function store(CreateReportExportRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $export  = $this->service->create(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data'     => [
                'id'          => $export->id,
                'report_type' => $export->report_type,
                'format'      => $export->format,
                'status'      => $export->status,
                'created_at'  => $export->created_at ? $export->created_at->toIso8601String() : null,
            ],
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->find((int) $id, $request->user()->id);

        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }
}
