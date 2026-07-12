<?php

namespace App\Http\Controllers\Api\V1\IngredientCategories;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\IngredientCategories\IngredientCategoryTreeResource;
use App\Services\IngredientCategories\IngredientCategoryService;
use Illuminate\Http\Request;

class IngredientCategoryCatalogController extends Controller
{
    private $service;

    public function __construct(IngredientCategoryService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => IngredientCategoryTreeResource::collection($this->service->catalog()),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
