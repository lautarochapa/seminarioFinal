<?php

namespace App\Http\Controllers\Api\V1\RecipeShoppingList;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecipeShoppingList\GenerateRecipeShoppingListRequest;
use App\Http\Resources\Api\V1\ShoppingLists\ShoppingListResource;
use App\Services\RecipeShoppingList\RecipeShoppingListService;
use Illuminate\Http\JsonResponse;

class RecipeShoppingListController extends Controller
{
    private $service;

    public function __construct(RecipeShoppingListService $service)
    {
        $this->service = $service;
    }

    public function store(GenerateRecipeShoppingListRequest $request, int $groupId, int $recipeId): JsonResponse
    {
        $traceId = $request->attributes->get('trace_id');

        $result = $this->service->generate(
            $request->user(),
            $groupId,
            $recipeId,
            $request->validated(),
            $request->ip(),
            $request->userAgent() ?? ''
        );

        return response()->json([
            'data' => [
                'shopping_list'           => new ShoppingListResource($result['shopping_list']),
                'items_added'             => $result['items_added'],
                'items_skipped_duplicate' => $result['items_skipped_duplicate'],
                'unmapped_ingredients'    => $result['unmapped_ingredients'],
                'priced_items'            => $result['priced_items'],
                'estimated_total'         => $result['estimated_total'],
                'items_without_price'     => $result['items_without_price'],
                'warnings'                => $result['warnings'],
            ],
            'trace_id' => $traceId,
        ], $result['created'] ? 201 : 200)->header('X-Trace-Id', $traceId);
    }
}
