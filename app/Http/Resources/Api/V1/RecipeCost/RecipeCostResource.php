<?php

namespace App\Http\Resources\Api\V1\RecipeCost;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeCostResource extends JsonResource
{
    public function toArray($request)
    {
        return $this->resource;
    }
}
