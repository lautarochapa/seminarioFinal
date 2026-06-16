<?php

namespace App\Http\Resources\Api\V1\Objectives;

use Illuminate\Http\Resources\Json\JsonResource;

class UserObjectiveResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'objective' => $this->whenLoaded('objective', function () {
                return [
                    'id' => $this->objective->id,
                    'code' => $this->objective->code,
                    'name' => $this->objective->name,
                    'description' => $this->objective->description,
                ];
            }),
            'priority' => $this->priority,
            'target_value' => is_null($this->target_value) ? null : (float) $this->target_value,
            'target_unit' => $this->target_unit,
            'target_date' => $this->target_date,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
