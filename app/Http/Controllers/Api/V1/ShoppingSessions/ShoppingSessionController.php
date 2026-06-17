<?php

namespace App\Http\Controllers\Api\V1\ShoppingSessions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShoppingSessions\ScanShoppingSessionRequest;
use App\Http\Requests\Api\V1\ShoppingSessions\UpdateShoppingSessionRequest;
use App\Http\Resources\Api\V1\ShoppingSessions\ShoppingSessionResource;
use App\Http\Resources\Api\V1\ShoppingSessions\ShoppingSessionScanResource;
use App\Services\ShoppingSessions\ShoppingSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingSessionController extends Controller
{
    private $service;

    public function __construct(ShoppingSessionService $service)
    {
        $this->service = $service;
    }

    public function start(Request $request, int $id, int $listId): JsonResponse
    {
        return response()->json([
            'data' => new ShoppingSessionResource($this->service->start($request->user(), $id, $listId, $request->ip(), $request->userAgent() ?? '')),
            'trace_id' => $request->attributes->get('trace_id'),
        ], 201);
    }

    public function update(UpdateShoppingSessionRequest $request, int $id, int $sessionId): JsonResponse
    {
        return response()->json([
            'data' => new ShoppingSessionResource($this->service->update($request->user(), $id, $sessionId, $request->validated(), $request->ip(), $request->userAgent() ?? '')),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }

    public function scan(ScanShoppingSessionRequest $request, int $id, int $sessionId): JsonResponse
    {
        return response()->json([
            'data' => new ShoppingSessionScanResource($this->service->scan($request->user(), $id, $sessionId, $request->validated(), $request->ip(), $request->userAgent() ?? '')),
            'trace_id' => $request->attributes->get('trace_id'),
        ], 201);
    }

    public function finish(Request $request, int $id, int $sessionId): JsonResponse
    {
        return response()->json([
            'data' => new ShoppingSessionResource($this->service->finish($request->user(), $id, $sessionId, $request->ip(), $request->userAgent() ?? '')),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }
}
