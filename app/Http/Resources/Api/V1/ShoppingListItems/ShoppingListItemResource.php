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
            'actual_price' => $this->actual_price,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
