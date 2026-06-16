<?php

namespace App\Http\Resources\Api\V1\Ingredients;

use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'normalized_name' => $this->normalized_name,
            'category_id' => $this->category_id,
            'base_unit_id' => $this->base_unit_id,
            'description' => $this->description,
            'is_generic' => (bool) $this->is_generic,
            'is_preparation' => (bool) $this->is_preparation,
            'is_supplement' => (bool) $this->is_supplement,
            'status' => $this->status,
            'category' => $this->whenLoaded('category', function () {
                return $this->category ? [
                    'id' => $this->category->id,
                    'code' => $this->category->code,
                    'name' => $this->category->name,
                ] : null;
            }),
            'base_unit' => $this->whenLoaded('baseUnit', function () {
                return $this->baseUnit ? [
                    'id' => $this->baseUnit->id,
                    'code' => $this->baseUnit->code,
                    'name' => $this->baseUnit->name,
                    'symbol' => $this->baseUnit->symbol,
                ] : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
