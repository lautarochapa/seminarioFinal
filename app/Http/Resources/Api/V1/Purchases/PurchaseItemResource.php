<?php

namespace App\Http\Resources\Api\V1\Purchases;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'product_id'      => $this->product_id,
            'product_name'    => $this->whenLoaded('product', function () {
                return $this->product ? $this->product->name : null;
            }),
            'quantity'        => (float) $this->quantity,
            'unit_id'         => $this->unit_id,
            'unit_name'       => $this->whenLoaded('unit', function () {
                return $this->unit ? $this->unit->name : null;
            }),
            'unit_price'      => $this->unit_price !== null ? (float) $this->unit_price : null,
            'total_price'     => $this->total_price !== null ? (float) $this->total_price : null,
            'expiration_date' => $this->expiration_date ? $this->expiration_date->toDateString() : null,
        ];
    }
}
