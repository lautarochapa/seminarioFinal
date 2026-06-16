<?php

namespace App\Http\Resources\Api\V1\StockAlerts;

use Illuminate\Http\Resources\Json\JsonResource;

class StockAlertResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'family_group_id' => $this->family_group_id,
            'stock_item_id' => $this->stock_item_id,
            'product_id' => $this->product_id,
            'alert_type' => $this->alert_type,
            'message' => $this->message,
            'severity' => $this->severity,
            'status' => $this->status,
            'read_at' => $this->read_at,
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
            'created_at' => $this->created_at,
        ];
    }
}
