<?php

namespace App\Http\Resources\Api\V1\Recipes;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeIngredientResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'ingredient_id'       => $this->ingredient_id,
            'ingredient_name'     => $this->whenLoaded('ingredient', function () {
                return $this->ingredient->name ?? null;
            }),
            'quantity'            => $this->quantity,
            'unit_id'             => $this->unit_id,
            'unit_name'           => $this->whenLoaded('unit', function () {
                return $this->unit->name ?? null;
            }),
            'is_optional'         => $this->is_optional,
            'notes'               => $this->notes,
            'sort_order'          => $this->sort_order,
        ];
    }
}
