<?php

namespace App\Http\Resources\Api\V1\HealthPreferences;

use Illuminate\Http\Resources\Json\JsonResource;

class CatalogHealthPreferenceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
