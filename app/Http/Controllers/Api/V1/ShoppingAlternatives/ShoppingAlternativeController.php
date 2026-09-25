<?php

namespace App\Http\Controllers\Api\V1\ShoppingAlternatives;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShoppingAlternatives\SelectShoppingAlternativeRequest;
use App\Http\Resources\Api\V1\ShoppingListItems\ShoppingListItemResource;
use App\Services\ShoppingAlternatives\ShoppingAlternativeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingAlternativeController extends Controller
{
    private $service;

    public function __construct(ShoppingAlternativeService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, int $id, int $listId): JsonResponse
    {
        return response()->json([
            'data' => $this->service->list($request->user(), $id, $listId),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }

    public function select(SelectShoppingAlternativeRequest $request, int $id, int $listId, int $itemId): JsonResponse
    {
        $data = $request->validated();
        $item = $this->service->select(
            $request->user(),
            $id,
            $listId,
            $itemId,
            isset($data['alternative_id']) ? (int) $data['alternative_id'] : null,
            $request->ip(),
            $request->userAgent() ?? '',
            isset($data['supermarket_product_id']) ? (int) $data['supermarket_product_id'] : null
        );

        return response()->json([
            'data' => new ShoppingListItemResource($item),
            'trace_id' => $request->attributes->get('trace_id'),
        ]);
    }
}
