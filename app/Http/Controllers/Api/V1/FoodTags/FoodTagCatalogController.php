<?php

namespace App\Http\Controllers\Api\V1\FoodTags;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FoodTags\FoodTagResource;
use App\Services\FoodTags\FoodTagService;
use Illuminate\Http\Request;

class FoodTagCatalogController extends Controller
{
    private $service;

    public function __construct(FoodTagService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $paginator = $this->service->publicList($request->query());

        return response()->json([
            'data' => FoodTagResource::collection($paginator),
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
