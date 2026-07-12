<?php

namespace App\Http\Resources\Api\V1\SupermarketBranches;

use Illuminate\Http\Resources\Json\JsonResource;

class SupermarketBranchResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id'                  => $this->id,
            'name'                => $this->name,
            'address'             => $this->address,
            'latitude'            => $this->latitude,
            'longitude'           => $this->longitude,
            'opening_hours'       => $this->opening_hours,
            'delivery_available'  => (bool) $this->delivery_available,
            'pickup_available'    => (bool) $this->pickup_available,
            'status'              => $this->status,
            'chain'               => $this->whenLoaded('chain', function () {
                return $this->chain ? [
                    'id'   => $this->chain->id,
                    'name' => $this->chain->name,
                ] : null;
            }),
            'city'                => $this->whenLoaded('city', function () {
                return $this->city ? [
                    'id'       => $this->city->id,
                    'name'     => $this->city->name,
                    'province' => $this->city->province,
                ] : null;
            }),
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];

        if (isset($this->resource->distance_km)) {
            $data['distance_km'] = $this->resource->distance_km;
        }

        return $data;
    }
}
