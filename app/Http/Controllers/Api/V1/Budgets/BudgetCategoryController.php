<?php

namespace App\Http\Controllers\Api\V1\Budgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Budgets\CreateBudgetCategoryRequest;
use App\Http\Requests\Api\V1\Budgets\UpdateBudgetCategoryRequest;
use App\Services\Budgets\BudgetCategoryService;
use Illuminate\Http\Request;

class BudgetCategoryController extends Controller
{
    private $service;

    public function __construct(BudgetCategoryService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id, $budgetId)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->list((int) $id, (int) $budgetId, $request->user()->id);

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function store(CreateBudgetCategoryRequest $request, $id, $budgetId)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->create(
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

    public function update(UpdateBudgetCategoryRequest $request, $id, $budgetId, $categoryId)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->update(
            (int) $id,
            (int) $budgetId,
            (int) $categoryId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => $data, 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $budgetId, $categoryId)
    {
        $traceId = $request->attributes->get('trace_id');
        $this->service->delete(
            (int) $id,
            (int) $budgetId,
            (int) $categoryId,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(null, 204)->header('X-Trace-Id', $traceId);
    }
}
