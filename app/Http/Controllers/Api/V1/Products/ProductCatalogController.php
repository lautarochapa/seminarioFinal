<?php

namespace App\Http\Controllers\Api\V1\Products;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Nutrients\ProductNutrientResource;
use App\Http\Resources\Api\V1\Products\ProductPriceResource;
use App\Http\Resources\Api\V1\Products\ProductResource;
use App\Services\Products\ProductService;
use Illuminate\Http\Request;

class ProductCatalogController extends Controller
{
    private $service;

    public function __construct(ProductService $service)
    {
        $this->service = $service;
    }

    public function findByBarcode(Request $request, $barcode)
    {
        $traceId = $request->attributes->get('trace_id');
        $barcode = trim((string) $barcode);

        return response()->json(['data' => new ProductResource($this->service->findByBarcode($barcode)), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function index(Request $request)
    {
        return $this->paginated($request, $this->service->publicList($request->query()));
    }

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json(['data' => new ProductResource($this->service->publicShow((int) $id)), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function nutrition(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json(['data' => ProductNutrientResource::collection($this->service->nutrition((int) $id)), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function prices(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json(['data' => ProductPriceResource::collection($this->service->prices((int) $id)), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    public function alternatives(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json(['data' => ProductResource::collection($this->service->alternatives((int) $id)), 'trace_id' => $traceId])
            ->header('X-Trace-Id', $traceId);
    }

    private function paginated(Request $request, $paginator)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => ProductResource::collection($paginator),
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
