<?php

namespace App\Http\Controllers\Api\V1\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Purchases\CreatePurchaseRequest;
use App\Http\Requests\Api\V1\Purchases\ListPurchasesRequest;
use App\Http\Requests\Api\V1\Purchases\UpdatePurchaseRequest;
use App\Http\Resources\Api\V1\Purchases\PurchaseResource;
use App\Services\Purchases\PurchaseService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    private $service;

    public function __construct(PurchaseService $service)
    {
        $this->service = $service;
    }

    public function index(ListPurchasesRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $page    = $this->service->list((int) $id, $request->user()->id, $request->validated());

        return response()->json([
            'data'     => PurchaseResource::collection($page),
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

    public function show(Request $request, $id, $purchaseId)
    {
        $traceId  = $request->attributes->get('trace_id');
        $purchase = $this->service->show((int) $id, (int) $purchaseId, $request->user()->id);

        return response()->json([
            'data'     => new PurchaseResource($purchase),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(CreatePurchaseRequest $request, $id)
    {
        $traceId  = $request->attributes->get('trace_id');
        $purchase = $this->service->create(
            (int) $id,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PurchaseResource($purchase),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdatePurchaseRequest $request, $id, $purchaseId)
    {
        $traceId  = $request->attributes->get('trace_id');
        $purchase = $this->service->update(
            (int) $id,
            (int) $purchaseId,
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PurchaseResource($purchase),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id, $purchaseId)
    {
        $traceId  = $request->attributes->get('trace_id');
        $purchase = $this->service->cancel(
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
