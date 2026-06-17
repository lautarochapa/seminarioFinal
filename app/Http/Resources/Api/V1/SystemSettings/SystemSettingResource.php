<?php

namespace App\Http\Resources\Api\V1\SystemSettings;

use App\Services\SystemSettings\SystemSettingService;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => app(SystemSettingService::class)->displayValue($this->resource),
            'type' => $this->type,
            'description' => $this->description,
            'is_public' => (bool) $this->is_public,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
