<?php

namespace App\Http\Controllers\Api\V1\Nutrients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Nutrients\StoreProductNutrientRequest;
use App\Http\Resources\Api\V1\Nutrients\ProductNutrientResource;
use App\Services\Nutrients\ProductNutrientService;
use Illuminate\Http\Request;

class ProductNutrientController extends Controller
{
    private $service;

    public function __construct(ProductNutrientService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $productId)
    {
        $traceId = $request->attributes->get('trace_id');
        $items   = $this->service->list((int) $productId);

        return response()->json([
            'data'     => ProductNutrientResource::collection($items),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StoreProductNutrientRequest $request, $productId)
    {
        $traceId  = $request->attributes->get('trace_id');
        $relation = $this->service->attach(
            $request->user()->id,
            (int) $productId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ProductNutrientResource($relation),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }
}
