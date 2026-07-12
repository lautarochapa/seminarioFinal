<?php

namespace App\Http\Controllers\Api\V1\StockAlerts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StockAlerts\StoreStockMinimumRuleRequest;
use App\Http\Requests\Api\V1\StockAlerts\UpdateStockMinimumRuleRequest;
use App\Http\Resources\Api\V1\StockAlerts\StockMinimumRuleResource;
use App\Services\StockAlerts\StockAlertService;
use Illuminate\Http\Request;

class StockMinimumRuleController extends Controller
{
    private $service;

    public function __construct(StockAlertService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id)
    {
        return $this->paginated($request, $this->service->rules((int) $id, $request->user()->id, $request->query()));
    }

    public function store(StoreStockMinimumRuleRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $rule = $this->service->createRule((int) $id, $request->user()->id, $request->validated(), $request->ip(), $request->userAgent());

        return response()->json([
            'data' => new StockMinimumRuleResource($rule),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id, $ruleId)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => new StockMinimumRuleResource($this->service->showRule((int) $id, (int) $ruleId, $request->user()->id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateStockMinimumRuleRequest $request, $id, $ruleId)
    {
        $traceId = $request->attributes->get('trace_id');
        $rule = $this->service->updateRule((int) $id, (int) $ruleId, $request->user()->id, $request->validated(), $request->ip(), $request->userAgent());

        return response()->json([
            'data' => new StockMinimumRuleResource($rule),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $ruleId)
    {
        $traceId = $request->attributes->get('trace_id');
        $rule = $this->service->deleteRule((int) $id, (int) $ruleId, $request->user()->id, $request->ip(), $request->userAgent());

        return response()->json([
            'data' => new StockMinimumRuleResource($rule),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    private function paginated(Request $request, $paginator)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => StockMinimumRuleResource::collection($paginator),
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
