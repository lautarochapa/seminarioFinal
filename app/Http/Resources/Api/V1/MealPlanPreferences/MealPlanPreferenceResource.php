<?php

namespace App\Http\Resources\Api\V1\MealPlanPreferences;

use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanPreferenceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'family_group_id'   => $this->family_group_id,
            'avoid_repetition'  => (bool) $this->avoid_repetition,
            'respect_budget'    => (bool) $this->respect_budget,
            'respect_nutrition' => (bool) $this->respect_nutrition,
            'respect_stock'     => (bool) $this->respect_stock,
            'preferred_mode'    => $this->preferred_mode,
            'updated_at'        => $this->updated_at,
        ];
    }
}
