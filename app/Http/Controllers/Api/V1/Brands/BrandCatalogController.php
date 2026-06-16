<?php

namespace App\Http\Controllers\Api\V1\Brands;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Brands\BrandResource;
use App\Services\Brands\BrandService;
use Illuminate\Http\Request;

class BrandCatalogController extends Controller
{
    private $service;

    public function __construct(BrandService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $paginator = $this->service->publicList($request->query());

        return response()->json([
            'data' => BrandResource::collection($paginator),
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
