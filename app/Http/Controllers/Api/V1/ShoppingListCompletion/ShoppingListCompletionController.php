<?php

namespace App\Http\Controllers\Api\V1\ShoppingListCompletion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShoppingListCompletion\CompleteShoppingListRequest;
use App\Http\Resources\Api\V1\ShoppingListCompletion\ShoppingListCompletionResource;
use App\Services\ShoppingListCompletion\ShoppingListCompletionService;

class ShoppingListCompletionController extends Controller
{
    private $service;

    public function __construct(ShoppingListCompletionService $service)
    {
        $this->service = $service;
    }

    public function complete(CompleteShoppingListRequest $request, $id, $listId)
    {
        $traceId = $request->attributes->get('trace_id');
        $result = $this->service->complete(
            $request->user(),
            (int) $id,
            (int) $listId,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => new ShoppingListCompletionResource($result),
            'message' => 'Compra finalizada. Agregamos '.$result['summary']['items_added_to_stock_count'].' productos a Mi cocina.',
            'trace_id' => $traceId,
        ], 200)->header('X-Trace-Id', $traceId);
    }

    public function processPendingStock(CompleteShoppingListRequest $request, $id, $listId)
    {
        $traceId = $request->attributes->get('trace_id');
        $result = $this->service->repairPendingStock($request->user(), (int) $id, (int) $listId, $request->validated(), $request->ip(), $request->userAgent());
        return response()->json(['data' => new ShoppingListCompletionResource($result), 'message' => 'Procesamos '.$result['summary']['items_added_to_stock_count'].' artículos pendientes.', 'trace_id' => $traceId], 200)->header('X-Trace-Id', $traceId);
    }
}
