<?php

namespace App\Http\Controllers\Api\V1\ScrapingCandidates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ScrapingCandidates\AssignIngredientRequest;
use App\Http\Requests\Api\V1\ScrapingCandidates\BulkCreateAndApproveRequest;
use App\Http\Requests\Api\V1\ScrapingCandidates\CreateProductRequest;
use App\Http\Requests\Api\V1\ScrapingCandidates\MatchProductRequest;
use App\Http\Requests\Api\V1\ScrapingCandidates\RejectCandidateRequest;
use App\Http\Resources\Api\V1\ScrapingCandidates\ScrapedProductCandidateResource;
use App\Services\ScrapingCandidates\ScrapingCandidateService;
use Illuminate\Http\Request;

class ScrapingCandidateController extends Controller
{
    private $service;

    public function __construct(ScrapingCandidateService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->list($request->query());

        return response()->json([
            'data'  => ScrapedProductCandidateResource::collection($paginator),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'links' => [
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
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->show((int) $id);

        return response()->json([
            'data'     => new ScrapedProductCandidateResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function matchProduct(MatchProductRequest $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->matchProduct(
            $request->user()->id,
            (int) $id,
            (int) $request->validated()['product_id'],
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ScrapedProductCandidateResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function createProduct(CreateProductRequest $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->createProduct(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ScrapedProductCandidateResource($candidate),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }

    public function createAndApprove(CreateProductRequest $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->createAndApprove(
            $request->user()->id,
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ScrapedProductCandidateResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function bulkCreateAndApprove(BulkCreateAndApproveRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $ids     = array_map('intval', $request->validated()['candidate_ids']);

        $result = $this->service->bulkCreateAndApprove(
            $request->user()->id,
            $ids,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json(array_merge($result, [
            'trace_id' => $traceId,
        ]))->header('X-Trace-Id', $traceId);
    }

    public function assignIngredient(AssignIngredientRequest $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->assignIngredient(
            $request->user()->id,
            (int) $id,
            (int) $request->validated()['ingredient_id'],
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ScrapedProductCandidateResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function approve(Request $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->approve(
            $request->user()->id,
            (int) $id,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ScrapedProductCandidateResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function reject(RejectCandidateRequest $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $candidate = $this->service->reject(
            $request->user()->id,
            (int) $id,
            $request->validated()['reason'],
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data'     => new ScrapedProductCandidateResource($candidate),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
