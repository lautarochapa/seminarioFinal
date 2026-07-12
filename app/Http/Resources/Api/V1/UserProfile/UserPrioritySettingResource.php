<?php

namespace App\Http\Resources\Api\V1\UserProfile;

use Illuminate\Http\Resources\Json\JsonResource;

class UserPrioritySettingResource extends JsonResource
{
    public function toArray($request)
    {
        if (!$this->resource) {
            return [
                'health_weight'      => 0,
                'budget_weight'      => 0,
                'time_weight'        => 0,
                'stock_usage_weight' => 0,
                'preferred_mode'     => null,
            ];
        }

        return [
            'health_weight'      => (float) $this->resource->health_weight,
            'budget_weight'      => (float) $this->resource->budget_weight,
            'time_weight'        => (float) $this->resource->time_weight,
            'stock_usage_weight' => (float) $this->resource->stock_usage_weight,
            'preferred_mode'     => $this->resource->preferred_mode,
        ];
    }
}
