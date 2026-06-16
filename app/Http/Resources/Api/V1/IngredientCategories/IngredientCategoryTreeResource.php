<?php

namespace App\Http\Resources\Api\V1\IngredientCategories;

use Illuminate\Http\Resources\Json\JsonResource;

class IngredientCategoryTreeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'children' => self::collection($this->whenLoaded('activeChildren')),
        ];
    }
}
