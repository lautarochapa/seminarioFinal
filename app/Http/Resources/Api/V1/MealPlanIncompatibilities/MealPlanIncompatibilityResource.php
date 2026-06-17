<?php

namespace App\Http\Resources\Api\V1\MealPlanIncompatibilities;

use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanIncompatibilityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'meal_plan_id'         => $this->meal_plan_id,
            'meal_plan_item_id'    => $this->meal_plan_item_id,
            'user_id'              => $this->user_id,
            'incompatibility_type' => $this->incompatibility_type,
            'message'              => $this->message,
            'severity'             => $this->severity,
            'status'               => $this->status,
            'created_at'           => $this->created_at,
        ];
    }
}
