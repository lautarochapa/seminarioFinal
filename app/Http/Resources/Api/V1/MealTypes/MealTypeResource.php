<?php

namespace App\Http\Resources\Api\V1\MealTypes;

use Illuminate\Http\Resources\Json\JsonResource;

class MealTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'code'       => $this->code,
            'name'       => $this->name,
            'sort_order' => $this->sort_order,
            'status'     => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
