<?php

namespace App\Http\Resources\Api\V1\SupermarketProducts;

use Illuminate\Http\Resources\Json\JsonResource;

class SupermarketProductResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id'              => $this->id,
            'external_sku'    => $this->external_sku,
            'source_url'      => $this->source_url,
            'source_name'     => $this->source_name,
            'last_scraped_at' => $this->last_scraped_at,
            'status'          => $this->status,
            'product'         => $this->whenLoaded('product', function () {
                return $this->product ? [
                    'id'   => $this->product->id,
                    'name' => $this->product->name,
                ] : null;
            }),
            'branch'          => $this->whenLoaded('branch', function () {
                if (! $this->branch) {
                    return null;
                }
                return [
                    'id'      => $this->branch->id,
                    'name'    => $this->branch->name,
                    'address' => $this->branch->address,
                    'chain'   => $this->branch->chain ? [
                        'id'   => $this->branch->chain->id,
                        'name' => $this->branch->chain->name,
                    ] : null,
                ];
            }),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];

        if (isset($this->resource->current_price) && $this->resource->current_price) {
            $cp             = $this->resource->current_price;
            $data['current_price'] = [
                'price'      => $cp->price,
                'currency'   => $cp->currency,
                'scraped_at' => $cp->scraped_at,
            ];
        } else {
            $data['current_price'] = null;
        }

        return $data;
    }
}
