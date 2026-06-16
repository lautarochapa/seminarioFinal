<?php

namespace App\Http\Controllers\Api\V1\ProductCategories;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductCategories\ProductCategoryTreeResource;
use App\Services\ProductCategories\ProductCategoryService;
use Illuminate\Http\Request;

class ProductCategoryCatalogController extends Controller
{
    private $service;

    public function __construct(ProductCategoryService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => ProductCategoryTreeResource::collection($this->service->catalog()),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
