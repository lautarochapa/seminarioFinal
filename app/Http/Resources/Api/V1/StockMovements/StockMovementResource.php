<?php

namespace App\Http\Resources\Api\V1\StockMovements;

use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'family_group_id' => $this->family_group_id,
            'stock_item_id' => $this->stock_item_id,
            'product_id' => $this->product_id,
            'movement_type' => $this->movement_type,
            'quantity' => $this->quantity,
            'unit_id' => $this->unit_id,
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'product' => $this->whenLoaded('product', function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                ] : null;
            }),
            'location' => $this->whenLoaded('stockItem', function () {
                return $this->stockItem && $this->stockItem->relationLoaded('location') && $this->stockItem->location ? [
                    'id' => $this->stockItem->location->id,
                    'name' => $this->stockItem->location->name,
                ] : null;
            }),
            'user' => $this->whenLoaded('creator', function () {
                return $this->creator ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ] : null;
            }),
            'created_at' => $this->created_at,
        ];
    }
}
