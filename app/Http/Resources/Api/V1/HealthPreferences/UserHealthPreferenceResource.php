<?php

namespace App\Http\Resources\Api\V1\HealthPreferences;

use Illuminate\Http\Resources\Json\JsonResource;

class UserHealthPreferenceResource extends JsonResource
{
    public function toArray($request)
    {
        $item = $this->dietaryRestriction ?: ($this->healthCondition ?: $this->allergy);

        return [
            'id' => $this->id,
            'item' => $item ? [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'description' => $item->description,
            ] : null,
            'severity' => $this->severity ?? null,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
