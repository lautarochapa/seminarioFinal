<?php

namespace App\Http\Resources\Api\V1\FeatureFlags;

use Illuminate\Http\Resources\Json\JsonResource;

class FeatureFlagResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'enabled' => (bool) $this->enabled,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
