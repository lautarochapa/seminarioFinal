<?php

namespace App\Http\Resources\Api\V1\ShoppingListCompletion;

use App\Http\Resources\Api\V1\Purchases\PurchaseResource;
use App\Http\Resources\Api\V1\ShoppingLists\ShoppingListResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListCompletionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'purchase' => new PurchaseResource($this['purchase']),
            'shopping_list' => new ShoppingListResource($this['list']),
            'items_purchased_count' => $this['summary']['items_purchased_count'],
            'items_added_to_stock_count' => $this['summary']['items_added_to_stock_count'],
            'items_omitted_count' => $this['summary']['items_omitted_count'],
            'stock_items_created' => $this['summary']['stock_items_created'],
            'stock_items_updated' => $this['summary']['stock_items_updated'],
            'stock_movements_created' => $this['summary']['stock_movements_created'],
            'warnings' => $this['summary']['warnings'],
        ];
    }
}
