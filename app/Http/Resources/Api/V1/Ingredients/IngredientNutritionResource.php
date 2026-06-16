<?php

namespace App\Http\Resources\Api\V1\Ingredients;

use Illuminate\Http\Resources\Json\JsonResource;

class IngredientNutritionResource extends JsonResource
{
    public function toArray($request)
    {
        $nutrient = $this->whenLoaded('nutrient', function () {
            return $this->nutrient ? [
                'id' => $this->nutrient->id,
                'code' => $this->nutrient->code,
                'name' => $this->nutrient->name,
                'unit' => $this->nutrient->relationLoaded('unit') && $this->nutrient->unit ? [
                    'id' => $this->nutrient->unit->id,
                    'code' => $this->nutrient->unit->code,
                    'name' => $this->nutrient->unit->name,
                    'symbol' => $this->nutrient->unit->symbol,
                ] : null,
            ] : null;
        });

        return [
            'id' => $this->id,
            'nutrient_id' => $this->nutrient_id,
            'nutrient' => $nutrient,
            'amount_per_100g' => $this->amount_per_100g,
            'source' => $this->source,
            'status' => $this->status,
        ];
    }
}
