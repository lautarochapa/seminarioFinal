<?php

namespace App\Http\Resources\Api\V1\RecipeFavoritesCooked;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeCookLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'recipe_id'        => $this->recipe_id,
            'family_group_id'  => $this->family_group_id,
            'servings'         => $this->servings,
            'cooked_at'        => $this->cooked_at,
            'stock_discounted' => $this->stock_discounted,
            'notes'            => $this->notes,
            'created_at'       => $this->created_at,
            'recipe'           => $this->whenLoaded('recipe', function () {
                return [
                    'id'          => $this->recipe->id,
                    'name'        => $this->recipe->name,
                    'difficulty'  => $this->recipe->difficulty,
                    'servings'    => $this->recipe->servings,
                    'source_type' => $this->recipe->source_type,
                    'is_official' => $this->recipe->is_official,
                ];
            }),
        ];
    }
}
