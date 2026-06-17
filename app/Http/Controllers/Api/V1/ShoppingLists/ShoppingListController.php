<?php

namespace App\Http\Controllers\Api\V1\ShoppingLists;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShoppingLists\StoreShoppingListRequest;
use App\Http\Requests\Api\V1\ShoppingLists\UpdateShoppingListRequest;
use App\Http\Resources\Api\V1\ShoppingLists\ShoppingListResource;
use App\Services\ShoppingLists\ShoppingListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingListController extends Controller
{
    private $service;

    public function __construct(ShoppingListService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, int $id): JsonResponse
    {
        $lists = $this->service->list($request->user(), $id, $request->all());

        return response()->json([
            'data' => ShoppingListResource::collection($lists->items()),
            'meta' => [
                'current_page' => $lists->currentPage(),
                'per_page' => $lists->perPage(),
                'total' => $lists->total(),
                'last_page' => $lists->lastPage(),
            ],
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }

    public function show(Request $request, int $id, int $listId): JsonResponse
    {
        return response()->json([
            'data' => new ShoppingListResource($this->service->show($request->user(), $id, $listId)),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }

    public function store(StoreShoppingListRequest $request, int $id): JsonResponse
    {
        $list = $this->service->create($request->user(), $id, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new ShoppingListResource($list),
            'trace_id' => $request->attributes->get('trace_id'),
        ], 201);
    }

    public function update(UpdateShoppingListRequest $request, int $id, int $listId): JsonResponse
    {
        $list = $this->service->update($request->user(), $id, $listId, $request->validated(), $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new ShoppingListResource($list),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }

    public function destroy(Request $request, int $id, int $listId): JsonResponse
    {
        $list = $this->service->delete($request->user(), $id, $listId, $request->ip(), $request->userAgent() ?? '');

        return response()->json([
            'data' => new ShoppingListResource($list),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }
}
