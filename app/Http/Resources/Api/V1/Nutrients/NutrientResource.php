<?php

namespace App\Http\Resources\Api\V1\Nutrients;

use Illuminate\Http\Resources\Json\JsonResource;

class NutrientResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'name'        => $this->name,
            'unit_id'     => $this->unit_id,
            'description' => $this->description,
            'status'      => $this->status,
            'unit'        => $this->whenLoaded('unit', function () {
                return $this->unit ? [
                    'id'     => $this->unit->id,
                    'code'   => $this->unit->code,
                    'name'   => $this->unit->name,
                    'symbol' => $this->unit->symbol,
                ] : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
