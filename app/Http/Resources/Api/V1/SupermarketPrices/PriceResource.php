<?php

namespace App\Http\Resources\Api\V1\SupermarketPrices;

use Illuminate\Http\Resources\Json\JsonResource;

class PriceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'price'       => $this->price,
            'currency'    => $this->currency,
            'captured_at' => $this->scraped_at,
            'valid_from'  => $this->valid_from,
            'valid_to'    => $this->valid_to,
            'source'      => $this->source,
            'is_current'  => $this->valid_to === null && $this->status === 'active',
            'status'      => $this->status,
            'created_at'  => $this->created_at,
        ];
    }
}
