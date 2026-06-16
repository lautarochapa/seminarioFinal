<?php

namespace App\Http\Resources\Api\V1\PriceRefreshRequests;

use Illuminate\Http\Resources\Json\JsonResource;

class PriceRefreshRequestResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'product_id' => $this->product_id,
            'supermarket_chain_id' => $this->supermarket_chain_id,
            'supermarket_branch_id' => $this->supermarket_branch_id,
            'reason' => $this->reason,
            'status' => $this->status,
            'requested_at' => $this->requested_at,
            'processed_at' => $this->processed_at,
            'product' => $this->whenLoaded('product', function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'status' => $this->product->status,
                ] : null;
            }),
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ] : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
