<?php

namespace App\Http\Controllers\Api\V1\RecipeCategories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeCategories\RecipeCategoryRequest;
use App\Http\Resources\Api\V1\RecipeCategories\RecipeCategoryResource;
use App\Services\RecipeCategories\RecipeCategoryService;
use Illuminate\Http\Request;

class RecipeCategoryAdminController extends Controller
{
    private $service;

    public function __construct(RecipeCategoryService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->query());

        return response()->json([
            'data'     => RecipeCategoryResource::collection($paginator),
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
            'data'     => new RecipeCategoryResource($this->service->show((int) $id)),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(RecipeCategoryRequest $request)
    {
        $traceId  = $request->attributes->get('trace_id');
        $category = $this->service->create(
            $request->user()->id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeCategoryResource($category),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function update(RecipeCategoryRequest $request, $id)
    {
        $traceId  = $request->attributes->get('trace_id');
        $category = $this->service->update(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeCategoryResource($category),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function destroy(Request $request, $id)
    {
        $traceId  = $request->attributes->get('trace_id');
        $category = $this->service->delete(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeCategoryResource($category),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function restore(Request $request, $id)
    {
        $traceId  = $request->attributes->get('trace_id');
        $category = $this->service->restore(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data'     => new RecipeCategoryResource($category),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
