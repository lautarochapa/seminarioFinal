<?php

namespace App\Http\Resources\Api\V1\Ingredients;

use Illuminate\Http\Resources\Json\JsonResource;

class IngredientEquivalenceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'equivalence_type' => $this->equivalence_type,
            'conversion_factor' => $this->conversion_factor,
            'reason' => $this->reason,
            'status' => $this->status,
            'target_ingredient' => $this->whenLoaded('targetIngredient', function () {
                return $this->targetIngredient ? new IngredientResource($this->targetIngredient) : null;
            }),
        ];
    }
}
