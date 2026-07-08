<?php

namespace App\Http\Resources\Api\V1\ShoppingListItems;

use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'ingredient' => $this->ingredient ? [
                'id' => $this->ingredient->id,
                'name' => $this->ingredient->name,
            ] : null,
            'product' => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ] : null,
            'quantity' => $this->quantity,
            'unit' => $this->unit ? [
                'id' => $this->unit->id,
                'code' => $this->unit->code,
                'symbol' => $this->unit->symbol,
            ] : null,
            'estimated_price' => $this->estimated_price,
            'estimated_subtotal' => $this->estimated_price !== null ? round((float) $this->estimated_price * (float) $this->quantity, 2) : null,
            'actual_price' => $this->actual_price,
            'status' => $this->status,
            'notes' => $this->notes,
            'price_source' => $this->price_source,
            'price_updated_at' => optional($this->price_updated_at)->toIso8601String(),
            'supermarket_chain_id' => $this->supermarket_chain_id,
            'supermarket_branch_id' => $this->supermarket_branch_id,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
