<?php

namespace App\Http\Controllers\Api\V1\RecipeCategories;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RecipeCategories\RecipeCategoryTreeResource;
use App\Services\RecipeCategories\RecipeCategoryService;
use Illuminate\Http\Request;

class RecipeCategoryCatalogController extends Controller
{
    private $service;

    public function __construct(RecipeCategoryService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => RecipeCategoryTreeResource::collection($this->service->catalog()),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
