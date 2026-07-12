<?php

namespace App\Http\Controllers\Api\V1\Scraping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Scraping\StoreSourceRequest;
use App\Http\Resources\Api\V1\Scraping\ScrapingSourceResource;
use App\Services\Scraping\ScrapingService;
use Illuminate\Http\Request;

class ScrapingSourceController extends Controller
{
    private $service;

    public function __construct(ScrapingService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->listSources($request->query());

        return response()->json([
            'data'  => ScrapingSourceResource::collection($paginator),
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

    public function store(StoreSourceRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $source  = $this->service->createSource(
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'data'     => new ScrapingSourceResource($source),
            'trace_id' => $traceId,
        ], 201)->header('X-Trace-Id', $traceId);
    }
}
