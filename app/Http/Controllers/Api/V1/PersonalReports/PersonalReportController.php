<?php

namespace App\Http\Controllers\Api\V1\PersonalReports;

use App\Http\Controllers\Controller;
use App\Services\PersonalReports\PersonalReportService;
use Illuminate\Http\Request;

class PersonalReportController extends Controller
{
    private $service;

    public function __construct(PersonalReportService $service)
    {
        $this->service = $service;
    }

    public function bodyProgress(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->bodyProgress($request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function objectivesProgress(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->objectivesProgress($request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }
}
