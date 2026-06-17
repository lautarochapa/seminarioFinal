<?php

namespace App\Http\Controllers\Api\V1\ShoppingListPreview;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ShoppingListPreview\GeneratedShoppingListResource;
use App\Http\Resources\Api\V1\ShoppingListPreview\ShoppingListPreviewResource;
use App\Services\ShoppingListPreview\ShoppingListPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingListPreviewController extends Controller
{
    private $service;

    public function __construct(ShoppingListPreviewService $service)
    {
        $this->service = $service;
    }

    public function preview(Request $request, int $id, int $planId): JsonResponse
    {
        $items = $this->service->preview($request->user(), $id, $planId);

        return response()->json([
            'data' => ShoppingListPreviewResource::collection(collect($items)),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }

    public function generate(Request $request, int $id, int $planId): JsonResponse
    {
        $result = $this->service->generate(
            $request->user(),
            $id,
            $planId,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => new GeneratedShoppingListResource($result['list']),
            'trace_id' => $request->attributes->get('trace_id'),
        ], $result['created'] ? 201 : 200);
    }
}
