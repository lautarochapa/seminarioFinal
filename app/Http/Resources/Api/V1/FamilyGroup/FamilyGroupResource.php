<?php

namespace App\Http\Resources\Api\V1\FamilyGroup;

use Illuminate\Http\Resources\Json\JsonResource;

class FamilyGroupResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'owner_user_id'     => $this->owner_user_id,
            'city_id'           => $this->city_id,
            'default_address'   => $this->default_address,
            'default_latitude'  => $this->default_latitude,
            'default_longitude' => $this->default_longitude,
            'status'            => $this->status,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'deleted_at'        => $this->deleted_at,
        ];
    }
}
