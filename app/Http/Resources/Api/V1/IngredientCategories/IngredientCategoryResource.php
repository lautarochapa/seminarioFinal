<?php

namespace App\Http\Resources\Api\V1\IngredientCategories;

use Illuminate\Http\Resources\Json\JsonResource;

class IngredientCategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'children_count' => $this->when(isset($this->children_count), $this->children_count),
            'ingredients_count' => $this->when(isset($this->ingredients_count), $this->ingredients_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
