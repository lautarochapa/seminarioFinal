<?php

namespace App\Http\Resources\Api\V1\HouseholdStock;

use Illuminate\Http\Resources\Json\JsonResource;

class StockItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'family_group_id' => $this->family_group_id,
            'product_id' => $this->product_id,
            'stock_location_id' => $this->stock_location_id,
            'quantity' => $this->quantity,
            'unit_id' => $this->unit_id,
            'expiration_date' => $this->expiration_date ? $this->expiration_date->toDateString() : null,
            'purchase_price' => $this->estimated_purchase_price,
            'status' => $this->status,
            'product' => $this->whenLoaded('product', function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                ] : null;
            }),
            'location' => $this->whenLoaded('location', function () {
                return $this->location ? [
                    'id' => $this->location->id,
                    'name' => $this->location->name,
                    'type' => $this->location->type,
                ] : null;
            }),
            'unit' => $this->whenLoaded('unit', function () {
                return $this->unit ? [
                    'id' => $this->unit->id,
                    'code' => $this->unit->code,
                    'name' => $this->unit->name,
                    'symbol' => $this->unit->symbol,
                ] : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
