<?php

namespace App\Http\Controllers\Api\V1\RecipeImportUrl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeImportUrl\RecipeImportUrlRequest;
use App\Http\Resources\Api\V1\RecipeImportUrl\ImportedRecipeCandidateResource;
use App\Services\RecipeImportUrl\RecipeImportUrlService;

class RecipeImportUrlController extends Controller
{
    private RecipeImportUrlService $service;

    public function __construct(RecipeImportUrlService $service)
    {
        $this->service = $service;
    }

    public function __invoke(RecipeImportUrlRequest $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->import(
            $request->user(),
            $request->input('url'),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ImportedRecipeCandidateResource($candidate),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }
}
