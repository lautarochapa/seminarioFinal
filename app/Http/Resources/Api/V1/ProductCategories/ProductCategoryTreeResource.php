<?php

namespace App\Http\Resources\Api\V1\ProductCategories;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryTreeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'children' => self::collection($this->whenLoaded('activeChildren')),
        ];
    }
}
