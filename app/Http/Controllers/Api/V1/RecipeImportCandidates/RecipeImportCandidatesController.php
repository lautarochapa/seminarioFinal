<?php

namespace App\Http\Controllers\Api\V1\RecipeImportCandidates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeImportCandidates\CreateRecipeFromCandidateRequest;
use App\Http\Requests\Api\V1\RecipeImportCandidates\MapIngredientRequest;
use App\Http\Requests\Api\V1\RecipeImportCandidates\RejectCandidateRequest;
use App\Http\Requests\Api\V1\RecipeImportCandidates\UpdateCandidateRequest;
use App\Http\Resources\Api\V1\RecipeImportCandidates\RecipeImportCandidateDetailResource;
use App\Http\Resources\Api\V1\Recipes\RecipeResource;
use App\Services\RecipeImportCandidates\RecipeImportCandidatesService;
use Illuminate\Http\Request;

class RecipeImportCandidatesController extends Controller
{
    private RecipeImportCandidatesService $service;

    public function __construct(RecipeImportCandidatesService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->user(), $request->query());

        return response()->json([
            'data'     => RecipeImportCandidateDetailResource::collection($paginator->items()),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function show(Request $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->show($request->user(), (int) $id);

        return response()->json([
            'data'     => new RecipeImportCandidateDetailResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function update(UpdateCandidateRequest $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->update(
            $request->user(),
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new RecipeImportCandidateDetailResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function mapIngredient(MapIngredientRequest $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->mapIngredient(
            $request->user(),
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new RecipeImportCandidateDetailResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function approve(Request $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->approve(
            $request->user(),
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new RecipeImportCandidateDetailResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function reject(RejectCandidateRequest $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->reject(
            $request->user(),
            (int) $id,
            $request->validated()['reason'] ?? null,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new RecipeImportCandidateDetailResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function createRecipe(CreateRecipeFromCandidateRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $recipe  = $this->service->createRecipe(
            $request->user(),
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new RecipeResource($recipe),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }
}
