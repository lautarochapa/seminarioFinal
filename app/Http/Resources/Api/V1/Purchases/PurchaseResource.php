<?php

namespace App\Http\Resources\Api\V1\Purchases;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                    => $this->id,
            'family_group_id'       => $this->family_group_id,
            'shopping_list_id'      => $this->shopping_list_id,
            'supermarket_branch_id' => $this->supermarket_branch_id,
            'user_id'               => $this->user_id,
            'payment_method_id'     => $this->payment_method_id,
            'purchase_date'         => $this->purchase_date ? $this->purchase_date->toDateString() : null,
            'estimated_total'       => $this->estimated_total !== null ? (float) $this->estimated_total : null,
            'actual_total'          => $this->actual_total !== null ? (float) $this->actual_total : null,
            'status'                => $this->status,
            'items'                 => PurchaseItemResource::collection($this->whenLoaded('items')),
            'created_at'            => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at'            => $this->updated_at ? $this->updated_at->toISOString() : null,
            'deleted_at'            => $this->deleted_at ? $this->deleted_at->toISOString() : null,
        ];
    }
}
