<?php

namespace App\Http\Controllers\Api\V1\Ingredients;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Ingredients\IngredientEquivalenceResource;
use App\Http\Resources\Api\V1\Ingredients\IngredientNutritionResource;
use App\Http\Resources\Api\V1\Ingredients\IngredientResource;
use App\Services\Ingredients\IngredientService;
use Illuminate\Http\Request;

class IngredientCatalogController extends Controller
{
    private $service;

    public function __construct(IngredientService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $paginator = $this->service->publicList($request->query());

        return response()->json([
            'data' => IngredientResource::collection($paginator),
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

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => new IngredientResource($this->service->publicShow((int) $id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function nutrition(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => IngredientNutritionResource::collection($this->service->nutrition((int) $id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function equivalences(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => IngredientEquivalenceResource::collection($this->service->equivalences((int) $id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
