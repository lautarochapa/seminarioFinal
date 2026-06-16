<?php

namespace App\Http\Resources\Api\V1\Supermarkets;

use Illuminate\Http\Resources\Json\JsonResource;

class SupermarketChainResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'website_url' => $this->website_url,
            'status'      => $this->status,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
