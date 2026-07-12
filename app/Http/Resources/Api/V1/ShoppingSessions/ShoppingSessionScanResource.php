<?php

namespace App\Http\Resources\Api\V1\ShoppingSessions;

use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingSessionScanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'product_id' => $this->product_id,
            'shopping_list_item_id' => $this->shopping_list_item_id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'scan_result' => $this->scan_result,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
