<?php

namespace App\Http\Controllers\Api\V1\RecipeSharingBranch;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RecipeSharingBranch\RecipeSharingBranchResource;
use App\Services\RecipeSharingBranch\RecipeSharingBranchService;
use Illuminate\Http\Request;

class RecipeSharingBranchController extends Controller
{
    private RecipeSharingBranchService $service;

    public function __construct(RecipeSharingBranchService $service)
    {
        $this->service = $service;
    }

    public function share(Request $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $recipe  = $this->service->share(
            $request->user(),
            (int) $recipeId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new RecipeSharingBranchResource($recipe),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function unshare(Request $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $recipe  = $this->service->unshare(
            $request->user(),
            (int) $recipeId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new RecipeSharingBranchResource($recipe),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function branch(Request $request, $recipeId)
    {
        $traceId = $request->attributes->get('trace_id');
        $recipe  = $this->service->branch(
            $request->user(),
            (int) $recipeId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new RecipeSharingBranchResource($recipe),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }
}
