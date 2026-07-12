<?php

namespace App\Http\Controllers\Api\V1\Scraping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Scraping\StoreJobRequest;
use App\Http\Resources\Api\V1\Scraping\ScrapingJobResource;
use App\Http\Resources\Api\V1\Scraping\ScrapingJobLogResource;
use App\Services\Scraping\ScrapingService;
use Illuminate\Http\Request;

class ScrapingJobController extends Controller
{
    private $service;

    public function __construct(ScrapingService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->listJobs($request->query());

        return response()->json([
            'data'  => ScrapingJobResource::collection($paginator),
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
        $traceId = $request->attributes->get('trace_id');
        $job     = $this->service->showJob((int) $id);

        return response()->json([
            'data'     => new ScrapingJobResource($job),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function store(StoreJobRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $job     = $this->service->createJob(
            $request->user()->id,
            $request->validated()
        );

        return response()->json([
            'data'     => new ScrapingJobResource($job),
            'trace_id' => $traceId,
        ], 202)->header('X-Trace-Id', $traceId);
    }

    public function retry(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $job     = $this->service->retryJob($request->user()->id, (int) $id);

        return response()->json([
            'data'     => new ScrapingJobResource($job),
            'trace_id' => $traceId,
        ], 202)->header('X-Trace-Id', $traceId);
    }

    public function cancel(Request $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $job     = $this->service->cancelJob($request->user()->id, (int) $id);

        return response()->json([
            'data'     => new ScrapingJobResource($job),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function logs(Request $request, $id)
    {
        $traceId   = $request->attributes->get('trace_id');
        $paginator = $this->service->jobLogs((int) $id, $request->query());

        return response()->json([
            'data'  => ScrapingJobLogResource::collection($paginator),
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
}
