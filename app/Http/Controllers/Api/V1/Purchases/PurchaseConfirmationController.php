<?php

namespace App\Http\Controllers\Api\V1\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Purchases\PurchaseResource;
use App\Services\Purchases\PurchaseConfirmationService;
use Illuminate\Http\Request;

class PurchaseConfirmationController extends Controller
{
    private $service;

    public function __construct(PurchaseConfirmationService $service)
    {
        $this->service = $service;
    }

    public function confirm(Request $request, $id, $purchaseId)
    {
        $traceId  = $request->attributes->get('trace_id');
        $purchase = $this->service->confirm(
            (int) $id,
            (int) $purchaseId,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PurchaseResource($purchase),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function addToStock(Request $request, $id, $purchaseId)
    {
        $traceId  = $request->attributes->get('trace_id');
        $purchase = $this->service->addToStock(
            (int) $id,
            (int) $purchaseId,
            $request->user()->id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PurchaseResource($purchase),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
