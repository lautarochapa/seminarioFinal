<?php

namespace App\Http\Resources\Api\V1\Objectives;

use Illuminate\Http\Resources\Json\JsonResource;

class CatalogObjectiveResource extends JsonResource
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
