<?php

namespace App\Http\Controllers\Api\V1\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchases\CreatePurchaseItemRequest;
use App\Http\Requests\Api\V1\Purchases\UpdatePurchaseItemRequest;
use App\Http\Resources\Api\V1\Purchases\PurchaseItemResource;
use App\Services\Purchases\PurchaseItemService;
use Illuminate\Http\Request;

class PurchaseItemController extends Controller
{
    private $service;

    public function __construct(PurchaseItemService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $id, $purchaseId)
    {
        $traceId = $request->attributes->get('trace_id');
        $items   = $this->service->list((int) $id, (int) $purchaseId, $request->user()->id);

        return response()->json([
            'data'     => PurchaseItemResource::collection($items),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(CreatePurchaseItemRequest $request, $id, $purchaseId)
    {
        $traceId = $request->attributes->get('trace_id');
        $item    = $this->service->create(
            (int) $id,
            (int) $purchaseId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PurchaseItemResource($item),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdatePurchaseItemRequest $request, $id, $purchaseId, $itemId)
    {
        $traceId = $request->attributes->get('trace_id');
        $item    = $this->service->update(
            (int) $id,
            (int) $purchaseId,
            (int) $itemId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PurchaseItemResource($item),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $purchaseId, $itemId)
    {
        $traceId = $request->attributes->get('trace_id');
        $this->service->delete(
            (int) $id,
            (int) $purchaseId,
            (int) $itemId,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(['data' => null, 'trace_id' => $traceId], 204)
            ->header('X-Trace-Id', $traceId);
    }
}
