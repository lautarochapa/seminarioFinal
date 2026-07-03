<?php

namespace App\Http\Resources\Api\V1\Supermarkets;

use Illuminate\Http\Resources\Json\JsonResource;

class SupermarketChainResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'website_url'    => $this->website_url,
            // La tabla supermarket_chains no tiene columna de logo; ver Requerimientos de base de datos.
            'logo_url'       => null,
            'branches_count' => $this->when(isset($this->branches_count), function () {
                return (int) $this->branches_count;
            }),
            'status'         => $this->status,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
