<?php

namespace App\Http\Controllers\Api\V1\ShoppingListItems;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShoppingListItems\StoreShoppingListItemRequest;
use App\Http\Requests\Api\V1\ShoppingListItems\UpdateShoppingListItemRequest;
use App\Http\Resources\Api\V1\ShoppingListItems\ShoppingListItemResource;
use App\Services\ShoppingListItems\ShoppingListItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingListItemController extends Controller
{
    private $service;

    public function __construct(ShoppingListItemService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, int $id, int $listId): JsonResponse
    {
        return response()->json([
            'data' => ShoppingListItemResource::collection($this->service->list($request->user(), $id, $listId)),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }

    public function store(StoreShoppingListItemRequest $request, int $id, int $listId): JsonResponse
    {
        return response()->json([
            'data' => new ShoppingListItemResource($this->service->create($request->user(), $id, $listId, $request->validated(), $request->ip(), $request->userAgent() ?? '')),
            'trace_id' => $request->attributes->get('trace_id'),
        ], 201);
    }

    public function update(UpdateShoppingListItemRequest $request, int $id, int $listId, int $itemId): JsonResponse
    {
        return response()->json([
            'data' => new ShoppingListItemResource($this->service->update($request->user(), $id, $listId, $itemId, $request->validated(), $request->ip(), $request->userAgent() ?? '')),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }

    public function destroy(Request $request, int $id, int $listId, int $itemId): JsonResponse
    {
        $this->service->delete($request->user(), $id, $listId, $itemId, $request->ip(), $request->userAgent() ?? '');

        return response()->json(null, 204);
    }
}
