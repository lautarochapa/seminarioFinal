<?php

namespace App\Http\Resources\Api\V1\WasteReports;

use Illuminate\Http\Resources\Json\JsonResource;

class WasteReportResource extends JsonResource
{
    public function toArray($request)
    {
        $quantity = abs((float) $this->quantity);
        $price = $this->stockItem ? $this->stockItem->estimated_purchase_price : null;

        return [
            'id' => $this->id,
            'type' => $this->movement_type,
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
            'reason' => $this->reason,
            'quantity' => $quantity,
            'unit' => $this->whenLoaded('unit', function () {
                return $this->unit ? [
                    'id' => $this->unit->id,
                    'code' => $this->unit->code,
                    'name' => $this->unit->name,
                    'symbol' => $this->unit->symbol,
                ] : null;
            }),
            'date' => $this->created_at,
            'estimated_loss' => $price !== null ? round($quantity * (float) $price, 2) : null,
        ];
    }
}
