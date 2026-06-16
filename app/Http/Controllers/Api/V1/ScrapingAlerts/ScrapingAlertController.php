<?php

namespace App\Http\Controllers\Api\V1\ScrapingAlerts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ScrapingAlerts\ResolveScrapingAlertRequest;
use App\Http\Resources\Api\V1\ScrapingAlerts\ScrapingAlertResource;
use App\Services\ScrapingAlerts\ScrapingAlertService;
use Illuminate\Http\Request;

class ScrapingAlertController extends Controller
{
    private $service;

    public function __construct(ScrapingAlertService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $paginator = $this->service->list($request->user(), $request->query());
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => ScrapingAlertResource::collection($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function resolve(ResolveScrapingAlertRequest $request, $id)
    {
        $traceId = $request->attributes->get('trace_id');
        $alert = $this->service->resolve(
            $request->user(),
            (int) $id,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?: ''
        );

        return response()->json([
            'data' => new ScrapingAlertResource($alert),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function report(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data' => $this->service->report($request->user(), $request->query()),
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
