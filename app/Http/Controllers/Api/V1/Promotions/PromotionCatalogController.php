<?php

namespace App\Http\Controllers\Api\V1\Promotions;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Promotions\PromotionResource;
use App\Services\Promotions\PromotionService;
use Illuminate\Http\Request;

class PromotionCatalogController extends Controller
{
    private $service;

    public function __construct(PromotionService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->catalog($request->query());

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
}
