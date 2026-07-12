<?php

namespace App\Http\Resources\Api\V1\SupermarketPrices;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceHistoryResource extends JsonResource
{
    public function toArray($request)
    {
        $supermarketProduct = $this->supermarketProduct;
        $branch = $supermarketProduct ? $supermarketProduct->branch : null;
        $chain = $branch ? $branch->chain : null;

        return [
            'id'          => $this->id,
            'price'       => $this->price,
            'currency'    => $this->currency,
            'captured_at' => $this->scraped_at,
            'valid_from'  => $this->valid_from,
            'valid_to'    => $this->valid_to,
            'is_current'  => $this->valid_to === null && $this->status === 'active',
            'status'      => $this->status,
            'source'      => $this->source,
            'chain'       => $chain ? [
                'id'   => $chain->id,
                'name' => $chain->name,
            ] : null,
            'branch'      => $branch ? [
                'id'      => $branch->id,
                'name'    => $branch->name,
                'address' => $branch->address,
            ] : null,
            'created_at'  => $this->created_at,
        ];
    }
}
