<?php

namespace App\Http\Controllers\Api\V1\Recipes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Recipes\RecipeRequest;
use App\Http\Resources\Api\V1\Recipes\RecipeResource;
use App\Services\Recipes\RecipeService;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    private $service;

    public function __construct(RecipeService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->query());

        return response()->json([
            'data'     => RecipeResource::collection($paginator),
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

    public function show(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => new RecipeResource($this->service->show((int) $id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(RecipeRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $recipe  = $this->service->create(
            $request->user(),
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeResource($recipe),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(RecipeRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $recipe  = $this->service->update(
            $request->user(),
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeResource($recipe),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $recipe  = $this->service->delete(
            $request->user(),
            (int) $id,
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeResource($recipe),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
