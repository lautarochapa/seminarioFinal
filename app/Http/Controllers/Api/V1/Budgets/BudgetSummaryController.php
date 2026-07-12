<?php

namespace App\Http\Controllers\Api\V1\Budgets;

use App\Http\Controllers\Controller;
use App\Services\Budgets\BudgetSummaryService;
use Illuminate\Http\Request;

class BudgetSummaryController extends Controller
{
    private $service;

    public function __construct(BudgetSummaryService $service)
    {
        $this->service = $service;
    }

    public function summary(Request $request, $id, $budgetId)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->summary((int) $id, (int) $budgetId, $request->user()->id);

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function projection(Request $request, $id, $budgetId)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->projection((int) $id, (int) $budgetId, $request->user()->id);

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }
}
