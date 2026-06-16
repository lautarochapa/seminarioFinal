<?php

namespace App\Http\Resources\Api\V1\Nutrients;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductNutrientResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'product_id'         => $this->product_id,
            'nutrient_id'        => $this->nutrient_id,
            'amount_per_100g'    => $this->amount_per_100g,
            'amount_per_serving' => $this->amount_per_serving,
            'serving_size'       => $this->serving_size,
            'source'             => $this->source,
            'status'             => $this->status,
            'nutrient'           => $this->whenLoaded('nutrient', function () {
                if (! $this->nutrient) {
                    return null;
                }
                return [
                    'id'   => $this->nutrient->id,
                    'code' => $this->nutrient->code,
                    'name' => $this->nutrient->name,
                    'unit' => $this->nutrient->relationLoaded('unit') && $this->nutrient->unit ? [
                        'id'     => $this->nutrient->unit->id,
                        'code'   => $this->nutrient->unit->code,
                        'name'   => $this->nutrient->unit->name,
                        'symbol' => $this->nutrient->unit->symbol,
                    ] : null,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
