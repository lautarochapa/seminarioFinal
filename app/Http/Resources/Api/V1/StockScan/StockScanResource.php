<?php

namespace App\Http\Resources\Api\V1\StockScan;

use Illuminate\Http\Resources\Json\JsonResource;

class StockScanResource extends JsonResource
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
            'status' => $this->status,
            'product' => $this->whenLoaded('product', function () {
                if (! $this->product) {
                    return null;
                }

                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'barcode' => $this->product->codigo,
                    'ingredient' => $this->product->relationLoaded('ingredient') && $this->product->ingredient ? [
                        'id' => $this->product->ingredient->id,
                        'name' => $this->product->ingredient->name,
                    ] : null,
                ];
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
        ];
    }
}
