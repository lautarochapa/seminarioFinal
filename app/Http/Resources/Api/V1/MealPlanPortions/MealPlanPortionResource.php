<?php

namespace App\Http\Resources\Api\V1\MealPlanPortions;

use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanPortionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'meal_plan_item_id' => $this->meal_plan_item_id,
            'user_id'           => $this->user_id,
            'user'              => $this->whenLoaded('user', fn() => [
                'id'    => $this->user->id,
                'email' => $this->user->email,
            ]),
            'portion_factor'    => $this->portion_factor,
            'servings'          => $this->servings,
            'notes'             => $this->notes,
            'created_at'        => optional($this->created_at)->toIso8601String(),
            'updated_at'        => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
