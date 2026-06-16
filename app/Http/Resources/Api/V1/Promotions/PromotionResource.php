<?php

namespace App\Http\Resources\Api\V1\Promotions;

use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                      => $this->id,
            'supermarket_chain_id'    => $this->supermarket_chain_id,
            'supermarket_branch_id'   => $this->supermarket_branch_id,
            'name'                    => $this->name,
            'description'             => $this->description,
            'discount_type'           => $this->discount_type,
            'discount_value'          => $this->discount_value,
            'valid_from'              => $this->valid_from,
            'valid_to'                => $this->valid_to,
            'day_of_week'             => $this->day_of_week,
            'requires_payment_method' => $this->requires_payment_method,
            'status'                  => $this->status,
            'chain'                   => $this->whenLoaded('chain', function () {
                return $this->chain ? [
                    'id'   => $this->chain->id,
                    'name' => $this->chain->name,
                ] : null;
            }),
            'branch'                  => $this->whenLoaded('branch', function () {
                return $this->branch ? [
                    'id'      => $this->branch->id,
                    'name'    => $this->branch->name,
                    'address' => $this->branch->address,
                ] : null;
            }),
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
            'deleted_at'              => $this->deleted_at,
        ];
    }
}
