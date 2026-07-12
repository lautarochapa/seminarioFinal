<?php

namespace App\Http\Controllers\Api\V1\PriceRefreshRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PriceRefreshRequests\StorePriceRefreshRequest;
use App\Http\Resources\Api\V1\PriceRefreshRequests\PriceRefreshRequestResource;
use App\Services\PriceRefreshRequests\PriceRefreshRequestService;
use Illuminate\Http\Request;

class PriceRefreshRequestController extends Controller
{
    private $service;

    public function __construct(PriceRefreshRequestService $service)
    {
        $this->service = $service;
    }

    public function store(StorePriceRefreshRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $refresh = $this->service->requestRefresh(
            $request->user(),
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => new PriceRefreshRequestResource($refresh),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function mine(Request $request)
    {
        return $this->paginated($request, $this->service->listForUser($request->user(), $request->query()));
    }

    public function adminIndex(Request $request)
    {
        return $this->paginated($request, $this->service->listAdmin($request->query()));
    }

    public function process(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $refresh = $this->service->process(
            $request->user(),
            (int) $id,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'data' => new PriceRefreshRequestResource($refresh),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    private function paginated(Request $request, $paginator)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => PriceRefreshRequestResource::collection($paginator),
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
