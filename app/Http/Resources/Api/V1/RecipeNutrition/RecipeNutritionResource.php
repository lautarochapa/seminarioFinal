<?php

namespace App\Http\Resources\Api\V1\RecipeNutrition;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeNutritionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                        => $this->id,
            'recipe_id'                 => $this->recipe_id,
            'calculation_status'        => $this->calculation_status,
            'calculated_at'             => $this->calculated_at,
            'calories_total'            => $this->calories_total,
            'calories_per_serving'      => $this->calories_per_serving,
            'protein_total'             => $this->protein_total,
            'protein_per_serving'       => $this->protein_per_serving,
            'carbohydrates_total'       => $this->carbohydrates_total,
            'carbohydrates_per_serving' => $this->carbohydrates_per_serving,
            'fat_total'                 => $this->fat_total,
            'fat_per_serving'           => $this->fat_per_serving,
            'sodium_total'              => $this->sodium_total,
            'sodium_per_serving'        => $this->sodium_per_serving,
            'sugar_total'               => $this->sugar_total,
            'sugar_per_serving'         => $this->sugar_per_serving,
            'fiber_total'               => $this->fiber_total,
            'fiber_per_serving'         => $this->fiber_per_serving,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
        ];
    }
}
