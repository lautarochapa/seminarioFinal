<?php

namespace App\Http\Resources\Api\V1\Products;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceResource extends JsonResource
{
    public function toArray($request)
    {
        $supermarketProduct = $this->whenLoaded('supermarketProduct', function () {
            return $this->supermarketProduct ? [
                'id' => $this->supermarketProduct->id,
                'source_name' => $this->supermarketProduct->source_name,
                'chain' => $this->supermarketProduct->relationLoaded('chain') && $this->supermarketProduct->chain ? [
                    'id' => $this->supermarketProduct->chain->id,
                    'name' => $this->supermarketProduct->chain->name,
                    'code' => $this->supermarketProduct->chain->code,
                ] : null,
            ] : null;
        });

        return [
            'id' => $this->id,
            'supermarket_product_id' => $this->supermarket_product_id,
            'price' => $this->price,
            'unit_price' => $this->unit_price,
            'currency' => $this->currency,
            'price_type' => $this->price_type,
            'scraped_at' => $this->scraped_at,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'source' => $this->source,
            'status' => $this->status,
            'supermarket_product' => $supermarketProduct,
            'created_at' => $this->created_at,
        ];
    }
}
