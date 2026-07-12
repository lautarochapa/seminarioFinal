<?php

namespace App\Http\Resources\Api\V1\RecipeSteps;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeStepResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'recipe_id'         => $this->recipe_id,
            'step_number'       => $this->step_number,
            'description'       => $this->description,
            'estimated_minutes' => $this->estimated_minutes,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
