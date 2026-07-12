<?php

namespace App\Http\Resources\Api\V1\RecipeFavoritesCooked;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeFavoriteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'recipe_id'   => $this->recipe_id,
            'favorited_at'=> $this->created_at,
            'recipe'      => $this->whenLoaded('recipe', function () {
                return [
                    'id'                 => $this->recipe->id,
                    'name'               => $this->recipe->name,
                    'difficulty'         => $this->recipe->difficulty,
                    'prep_time_minutes'  => $this->recipe->prep_time_minutes,
                    'cook_time_minutes'  => $this->recipe->cook_time_minutes,
                    'servings'           => $this->recipe->servings,
                    'source_type'        => $this->recipe->source_type,
                    'is_official'        => $this->recipe->is_official,
                    'is_public'          => $this->recipe->is_public,
                    'status'             => $this->recipe->status,
                ];
            }),
        ];
    }
}
