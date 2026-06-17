<?php

namespace App\Http\Controllers\Api\V1\RecipeScraping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeScraping\CreateRecipeScrapingJobRequest;
use App\Http\Resources\Api\V1\Scraping\ScrapingJobResource;
use App\Services\RecipeScraping\RecipeScrapingService;
use Illuminate\Http\Request;

class RecipeScrapingJobController extends Controller
{
    private RecipeScrapingService $service;

    public function __construct(RecipeScrapingService $service)
    {
        $this->service = $service;
    }

    public function store(CreateRecipeScrapingJobRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        try {
            $job = $this->service->create(
                $request->user(),
                $request->validated(),
                $request->ip(),
                $request->userAgent() ?? ''
            );
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e, $traceId);
        }

        return response()->json([
            'data'     => new ScrapingJobResource($job),
            'trace_id' => $traceId,
        ], 202)->header('X-Trace-Id', $traceId);
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->user(), $request->query());

        return response()->json([
            'data'     => ScrapingJobResource::collection($paginator->items()),
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
        $traceId = $request->attributes->get('trace_id');
        $job     = $this->service->show($request->user(), (int) $id);

        return response()->json([
            'data'     => new ScrapingJobResource($job),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function retry(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        try {
            $job = $this->service->retry(
                $request->user(),
                (int) $id,
                $request->ip(),
                $request->userAgent() ?? ''
            );
        } catch (\RuntimeException $e) {
            return $this->runtimeError($e, $traceId);
        }

        return response()->json([
            'data'     => new ScrapingJobResource($job),
            'trace_id' => $traceId,
        ], 202)->header('X-Trace-Id', $traceId);
    }

    private function runtimeError(\RuntimeException $e, ?string $traceId)
    {
        $parts  = explode(':', $e->getMessage(), 2);
        $code   = $parts[0];
        $msg    = $parts[1] ?? $e->getMessage();
        $status = str_contains($code, 'NOT_RETRYABLE') ? 422 : 409;

        return response()->json([
            'error'    => ['code' => $code, 'message' => $msg, 'details' => [], 'field_errors' => (object) []],
            'trace_id' => $traceId,
        ], $status)->header('X-Trace-Id', $traceId);
    }
}
