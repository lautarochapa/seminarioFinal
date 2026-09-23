<?php

namespace App\Http\Resources\Api\V1\ShoppingLists;

use App\Http\Resources\Api\V1\ShoppingListItems\ShoppingListItemResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'family_group_id' => $this->family_group_id,
            'meal_plan_id' => $this->meal_plan_id,
            'source_type' => $this->source_type,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'stock_repair_requires_review' => $this->stockRepairRequiresReview(),
            'estimated_total' => $this->estimated_total,
            'optimization_mode' => $this->optimization_mode,
            'items' => ShoppingListItemResource::collection($this->whenLoaded('items')),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'deleted_at' => optional($this->deleted_at)->toIso8601String(),
        ];
    }
}
