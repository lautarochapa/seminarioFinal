<?php

namespace App\Http\Controllers\Api\V1\Budgets;

use App\Http\Controllers\Controller;
use App\Services\Budgets\BudgetAlertService;
use Illuminate\Http\Request;

class BudgetAlertController extends Controller
{
    private $service;

    public function __construct(BudgetAlertService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id, $budgetId)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list(
            (int) $id,
            (int) $budgetId,
            $request->user()->id,
            $request->only(['alert_type', 'status', 'severity', 'per_page', 'page'])
        );

        return response()->json([
            'data'     => $paginator->items(),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links'    => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function markAsRead(Request $request, $id, $budgetId, $alertId)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->markAsRead(
            (int) $id,
            (int) $budgetId,
            (int) $alertId,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }
}
