<?php

namespace App\Http\Resources\Api\V1\StockAlerts;

use Illuminate\Http\Resources\Json\JsonResource;

class StockMinimumRuleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'family_group_id' => $this->family_group_id,
            'product_id' => $this->product_id,
            'ingredient_id' => $this->ingredient_id,
            'minimum_quantity' => $this->minimum_quantity,
            'unit_id' => $this->unit_id,
            'status' => $this->status,
            'product' => $this->whenLoaded('product', function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                ] : null;
            }),
            'ingredient' => $this->whenLoaded('ingredient', function () {
                return $this->ingredient ? [
                    'id' => $this->ingredient->id,
                    'name' => $this->ingredient->name,
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
        ];
    }
}
