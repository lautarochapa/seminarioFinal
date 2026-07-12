<?php

namespace App\Http\Resources\Api\V1\Scraping;

use Illuminate\Http\Resources\Json\JsonResource;

class ScrapingSourceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'code'       => $this->code,
            'name'       => $this->name,
            'type'       => $this->type,
            'base_url'   => $this->base_url,
            'city_id'    => $this->city_id,
            'is_active'  => $this->is_active,
            'status'     => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
