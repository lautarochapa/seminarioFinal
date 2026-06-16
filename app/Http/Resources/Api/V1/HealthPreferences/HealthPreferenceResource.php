<?php

namespace App\Http\Resources\Api\V1\HealthPreferences;

use Illuminate\Http\Resources\Json\JsonResource;

class HealthPreferenceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->created_at ?? null,
            'updated_at' => $this->updated_at ?? null,
            'deleted_at' => $this->deleted_at ?? null,
        ];
    }
}
