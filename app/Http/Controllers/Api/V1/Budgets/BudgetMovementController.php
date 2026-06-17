<?php

namespace App\Http\Controllers\Api\V1\Budgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Budgets\CreateAdjustmentRequest;
use App\Services\Budgets\BudgetMovementService;
use Illuminate\Http\Request;

class BudgetMovementController extends Controller
{
    private $service;

    public function __construct(BudgetMovementService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id, $budgetId)
    {
        $traceId  = $request->attributes->get('trace_id');
        $paginator = $this->service->list(
            (int) $id,
            (int) $budgetId,
            $request->user()->id,
            $request->only(['movement_type', 'per_page', 'page'])
        );

        $items = $paginator->items();

        return response()->json([
            'data'     => $items,
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

    public function storeAdjustment(CreateAdjustmentRequest $request, $id, $budgetId)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->createAdjustment(
            (int) $id,
            (int) $budgetId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $data, 'trace_id' => $traceId], 201)
            ->header('X-Trace-Id', $traceId);
    }
}
