<?php

namespace App\Http\Controllers\Api\V1\Promotions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Promotions\StorePromotionRequest;
use App\Http\Requests\Api\V1\Promotions\UpdatePromotionRequest;
use App\Http\Resources\Api\V1\Promotions\PromotionResource;
use App\Services\Promotions\PromotionService;
use Illuminate\Http\Request;

class AdminPromotionController extends Controller
{
    private $service;

    public function __construct(PromotionService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->query());

        return response()->json([
            'data'  => PromotionResource::collection($paginator),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $promotion = $this->service->show((int) $id);

        return response()->json([
            'data'     => new PromotionResource($promotion),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StorePromotionRequest $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $promotion = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PromotionResource($promotion),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(UpdatePromotionRequest $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $promotion = $this->service->update(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PromotionResource($promotion),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $promotion = $this->service->destroy(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PromotionResource($promotion),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $promotion = $this->service->restore(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new PromotionResource($promotion),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
