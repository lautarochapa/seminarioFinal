<?php

namespace App\Http\Resources\Api\V1\Professional;

use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'family_group_id' => $this->family_group_id,
            'period_type'     => $this->period_type,
            'start_date'      => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date'        => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'status'          => $this->status,
            'mode'            => $this->mode,
            'config_json'     => $this->config_json,
            'approved_at'     => $this->approved_at ? $this->approved_at->toIso8601String() : null,
            'created_at'      => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
