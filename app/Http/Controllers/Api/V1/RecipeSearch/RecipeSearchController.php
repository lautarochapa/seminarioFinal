<?php

namespace App\Http\Controllers\Api\V1\RecipeSearch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeSearch\RecipeSearchRequest;
use App\Http\Resources\Api\V1\RecipeSearch\RecipeSearchResource;
use App\Services\RecipeSearch\RecipeSearchService;

class RecipeSearchController extends Controller
{
    private RecipeSearchService $service;

    public function __construct(RecipeSearchService $service)
    {
        $this->service = $service;
    }

    public function __invoke(RecipeSearchRequest $request)
    {
        $traceId  = $request->attributes->get('trace_id');
        $paginator = $this->service->search($request->user(), $request->validated());

        return response()->json([
            'data'     => RecipeSearchResource::collection($paginator->items()),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links'    => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
