<?php

namespace App\Http\Resources\Api\V1\Units;

use App\Http\Resources\Api\V1\Ingredients\IngredientResource;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitConversionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'from_unit_id' => $this->from_unit_id,
            'to_unit_id' => $this->to_unit_id,
            'ingredient_id' => $this->ingredient_id,
            'factor' => $this->factor,
            'notes' => $this->notes,
            'status' => $this->status,
            'from_unit' => $this->whenLoaded('fromUnit', function () {
                return $this->fromUnit ? new UnitResource($this->fromUnit) : null;
            }),
            'to_unit' => $this->whenLoaded('toUnit', function () {
                return $this->toUnit ? new UnitResource($this->toUnit) : null;
            }),
            'ingredient' => $this->whenLoaded('ingredient', function () {
                return $this->ingredient ? new IngredientResource($this->ingredient) : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
