<?php

namespace App\Http\Resources\Api\V1\Consents;

use Illuminate\Http\Resources\Json\JsonResource;

class UserConsentStateResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'consents' => $this->resource['consents'],
            'required' => $this->resource['required'],
        ];
    }
}
