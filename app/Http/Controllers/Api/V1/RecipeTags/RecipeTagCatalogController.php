<?php

namespace App\Http\Controllers\Api\V1\RecipeTags;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RecipeTags\RecipeTagResource;
use App\Services\RecipeTags\RecipeTagService;
use Illuminate\Http\Request;

class RecipeTagCatalogController extends Controller
{
    private $service;

    public function __construct(RecipeTagService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => RecipeTagResource::collection($this->service->catalog()),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
