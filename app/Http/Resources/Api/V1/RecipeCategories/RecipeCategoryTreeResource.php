<?php

namespace App\Http\Resources\Api\V1\RecipeCategories;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeCategoryTreeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'children'    => self::collection($this->whenLoaded('activeChildren')),
        ];
    }
}
