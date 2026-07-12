<?php

namespace App\Http\Controllers\Api\V1\AdminReports;

use App\Http\Controllers\Controller;
use App\Services\AdminReports\AdminReportService;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    private $service;

    public function __construct(AdminReportService $service)
    {
        $this->service = $service;
    }

    public function usersActive(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->usersActive($request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function productsPendingReview(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->productsPendingReview($request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function recipesPendingReview(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->recipesPendingReview($request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function priceVariations(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->priceVariations($request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function mostUsedRecipes(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->mostUsedRecipes($request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }

    public function supermarketPriceStatus(Request $request)
    {
        $traceId = $request->attributes->get('trace_id');
        $data    = $this->service->supermarketPriceStatus($request->query->all());
        return response()->json(['data' => $data, 'trace_id' => $traceId])->header('X-Trace-Id', $traceId);
    }
}
