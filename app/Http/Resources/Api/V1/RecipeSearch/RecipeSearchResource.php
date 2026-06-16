<?php

namespace App\Http\Resources\Api\V1\RecipeSearch;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeSearchResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'difficulty'         => $this->difficulty,
            'prep_time_minutes'  => $this->prep_time_minutes,
            'cook_time_minutes'  => $this->cook_time_minutes,
            'servings'           => $this->servings,
            'source_type'        => $this->source_type,
            'is_official'        => $this->is_official,
            'is_public'          => $this->is_public,
            'status'             => $this->status,
            'category'           => $this->whenLoaded('category', function () {
                return $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null;
            }),
            'owner'              => $this->whenLoaded('owner', function () {
                return $this->owner ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null;
            }),
            'tags_count'         => $this->tags_count,
            'ingredients_count'  => $this->ingredients_count,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
