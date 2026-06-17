<?php

namespace App\Http\Resources\Api\V1\RecipeIngredients;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeIngredientResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'recipe_id'           => $this->recipe_id,
            'ingredient_id'       => $this->ingredient_id,
            'ingredient_name'     => $this->whenLoaded('ingredient', function () {
                return $this->ingredient->name ?? null;
            }),
            'unit_id'             => $this->unit_id,
            'unit_name'           => $this->whenLoaded('unit', function () {
                return $this->unit->name ?? null;
            }),
            'quantity'            => $this->quantity,
            'specific_product_id' => $this->specific_product_id,
            'notes'               => $this->notes,
            'is_optional'         => $this->is_optional,
            'sort_order'          => $this->sort_order,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
