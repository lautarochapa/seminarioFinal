<?php

namespace App\Http\Controllers\Api\V1\GroupReports;

use App\Http\Controllers\Controller;
use App\Services\GroupReports\GroupReportService;
use Illuminate\Http\Request;

class GroupReportController extends Controller
{
    private $service;

    public function __construct(GroupReportService $service)
    {
        $this->service = $service;
    }

    public function stock(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->stock((int) $id, $request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function stockValue(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->stockValue((int) $id, $request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function expiringProducts(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->expiringProducts((int) $id, $request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function waste(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->waste((int) $id, $request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function purchases(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->purchases((int) $id, $request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function budget(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->budget((int) $id, $request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function budgetVsActual(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->budgetVsActual((int) $id, $request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function recipesCooked(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->recipesCooked((int) $id, $request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function nutritionEstimate(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->nutritionEstimate((int) $id, $request->user()->id, $request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }
}
