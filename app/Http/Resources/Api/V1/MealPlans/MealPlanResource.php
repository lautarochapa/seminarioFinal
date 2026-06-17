<?php

namespace App\Http\Resources\Api\V1\MealPlans;

use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'family_group_id' => $this->family_group_id,
            'created_by'      => $this->created_by,
            'period_type'     => $this->period_type,
            'start_date'      => optional($this->start_date)->toDateString(),
            'end_date'        => optional($this->end_date)->toDateString(),
            'mode'            => $this->mode,
            'status'          => $this->status,
            'items'           => MealPlanItemResource::collection($this->whenLoaded('items')),
            'created_at'      => optional($this->created_at)->toIso8601String(),
            'updated_at'      => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
