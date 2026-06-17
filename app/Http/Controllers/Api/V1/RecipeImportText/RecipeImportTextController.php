<?php

namespace App\Http\Controllers\Api\V1\RecipeImportText;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeImportText\RecipeImportTextRequest;
use App\Http\Resources\Api\V1\RecipeImportUrl\ImportedRecipeCandidateResource;
use App\Services\RecipeImportText\RecipeImportTextService;

class RecipeImportTextController extends Controller
{
    private RecipeImportTextService $service;

    public function __construct(RecipeImportTextService $service)
    {
        $this->service = $service;
    }

    public function __invoke(RecipeImportTextRequest $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->import(
            $request->user(),
            $request->input('text'),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ImportedRecipeCandidateResource($candidate),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }
}
