<?php

namespace App\Http\Controllers\Api\V1\Budgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Budgets\CreateBudgetRequest;
use App\Http\Requests\Api\V1\Budgets\UpdateBudgetRequest;
use App\Http\Resources\Api\V1\Budgets\BudgetResource;
use App\Services\Budgets\BudgetService;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    private $service;

    public function __construct(BudgetService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $page    = $this->service->list((int) $id, $request->user()->id, $request->all());

        return response()->json([
            'data'     => BudgetResource::collection($page),
            'meta'     => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
            'links'    => [
                'first' => $page->url(1),
                'last'  => $page->url($page->lastPage()),
                'prev'  => $page->previousPageUrl(),
                'next'  => $page->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function current(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $budget  = $this->service->current((int) $id, $request->user()->id);

        if (!$budget) {
            return response()->json(['data' => null, 'trace_id' => $traceId])
                ->header('X-Trace-Id', $traceId);
        }

        $usage    = $this->service->withUsage($budget);
        $resource = (new BudgetResource($budget))
            ->withUsage($usage['used'], $usage['available'], $usage['percent']);

        return response()->json(['data' => $resource, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function store(CreateBudgetRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $budget  = $this->service->create(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new BudgetResource($budget),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateBudgetRequest $request, $id, $budgetId)
    {
        $traceId = $request->attributes->get('trace_id');
        $budget  = $this->service->update(
            (int) $id,
            (int) $budgetId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new BudgetResource($budget),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $budgetId)
    {
        $traceId = $request->attributes->get('trace_id');
        $budget  = $this->service->delete(
            (int) $id,
            (int) $budgetId,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new BudgetResource($budget),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
