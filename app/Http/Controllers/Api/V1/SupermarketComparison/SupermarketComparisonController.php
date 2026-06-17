<?php

namespace App\Http\Controllers\Api\V1\SupermarketComparison;

use App\Http\Controllers\Controller;
use App\Services\SupermarketComparison\SupermarketComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupermarketComparisonController extends Controller
{
    private $service;

    public function __construct(SupermarketComparisonService $service)
    {
        $this->service = $service;
    }

    public function compare(Request $request, int $id, int $listId): JsonResponse
    {
        return response()->json([
            'data' => $this->service->compare($request->user(), $id, $listId),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }

    public function optimize(Request $request, int $id, int $listId): JsonResponse
    {
        return response()->json([
            'data' => $this->service->optimize($request->user(), $id, $listId),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }
}
