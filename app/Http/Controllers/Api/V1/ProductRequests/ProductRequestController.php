<?php

namespace App\Http\Controllers\Api\V1\ProductRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductRequests\ApproveProductRequestRequest;
use App\Http\Requests\Api\V1\ProductRequests\RejectProductRequestRequest;
use App\Http\Requests\Api\V1\ProductRequests\StoreProductRequestRequest;
use App\Http\Resources\Api\V1\ProductRequests\ProductRequestResource;
use App\Services\ProductRequests\ProductRequestService;
use Illuminate\Http\Request;

class ProductRequestController extends Controller
{
    private $service;

    public function __construct(ProductRequestService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $paginator = $this->service->list($request->query(), $request->user());
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => ProductRequestResource::collection($paginator),
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

    public function store(StoreProductRequestRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $productRequest = $this->service->create($request->user(), $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new ProductRequestResource($productRequest),
            'message' => 'Solicitud enviada. Cuando el producto sea aprobado, vas a poder cargarlo al stock.',
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $productRequest = $this->service->show((int) $id, $request->user());

        return response()->json([
            'data' => new ProductRequestResource($productRequest),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function approve(ApproveProductRequestRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $productRequest = $this->service->approve($request->user(), (int) $id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new ProductRequestResource($productRequest),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function reject(RejectProductRequestRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $productRequest = $this->service->reject($request->user(), (int) $id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new ProductRequestResource($productRequest),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
