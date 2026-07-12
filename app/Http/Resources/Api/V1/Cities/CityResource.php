<?php

namespace App\Http\Resources\Api\V1\Cities;

use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'province'  => $this->province,
            'country'   => $this->country,
            'latitude'  => $this->latitude,
            'longitude' => $this->longitude,
            'status'    => $this->status,
            'created_at'=> $this->created_at,
            'updated_at'=> $this->updated_at,
        ];
    }
}
