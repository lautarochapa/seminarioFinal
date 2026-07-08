<?php

namespace App\Http\Resources\Api\V1\ProductRequests;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductRequestResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'requested_by_user_id' => $this->requested_by_user_id,
            'family_group_id' => $this->family_group_id,
            'name' => $this->name,
            'brand' => $this->brand,
            'presentation' => $this->presentation,
            'barcode' => $this->barcode,
            'unit_id' => $this->unit_id,
            'comment' => $this->comment,
            'source' => $this->source,
            'status' => $this->status,
            'product_id' => $this->product_id,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'reviewed_at' => $this->reviewed_at,
            'review_notes' => $this->review_notes,
            'requester' => $this->whenLoaded('requester', function () {
                return $this->requester ? [
                    'id' => $this->requester->id,
                    'name' => $this->requester->name,
                    'email' => $this->requester->email,
                ] : null;
            }),
            'family_group' => $this->whenLoaded('familyGroup', function () {
                return $this->familyGroup ? [
                    'id' => $this->familyGroup->id,
                    'name' => $this->familyGroup->name,
                ] : null;
            }),
            'unit' => $this->whenLoaded('unit', function () {
                return $this->unit ? [
                    'id' => $this->unit->id,
                    'code' => $this->unit->code,
                    'name' => $this->unit->name,
                    'symbol' => $this->unit->symbol,
                ] : null;
            }),
            'product' => $this->whenLoaded('product', function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'status' => $this->product->status,
                ] : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
