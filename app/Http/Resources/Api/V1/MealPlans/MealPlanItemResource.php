<?php

namespace App\Http\Resources\Api\V1\MealPlans;

use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'date'                 => optional($this->date)->toDateString(),
            'meal_type_id'         => $this->meal_type_id,
            'meal_type'            => $this->whenLoaded('mealType', fn () => [
                'id'   => $this->mealType->id,
                'code' => $this->mealType->code,
                'name' => $this->mealType->name,
            ]),
            'recipe_id'            => $this->recipe_id,
            'recipe'               => $this->whenLoaded('recipe', fn () => $this->recipe ? [
                'id'     => $this->recipe->id,
                'nombre' => $this->recipe->nombre,
            ] : null),
            'free_meal_description'=> $this->free_meal_description,
            'is_eating_out'        => (bool) $this->is_eating_out,
            'servings_total'       => $this->servings_total,
            'notes'                => $this->notes,
            'status'               => $this->status,
            'created_at'           => optional($this->created_at)->toIso8601String(),
        ];
    }
}
