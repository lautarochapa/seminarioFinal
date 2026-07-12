<?php

namespace App\Http\Resources\Api\V1\ShoppingLists;

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
            'estimated_total' => $this->estimated_total,
            'optimization_mode' => $this->optimization_mode,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'ingredient' => $item->ingredient ? [
                            'id' => $item->ingredient->id,
                            'name' => $item->ingredient->name,
                        ] : null,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                        ] : null,
                        'free_text_name' => $item->free_text_name,
                        'display_name' => $item->product ? $item->product->name : ($item->ingredient ? $item->ingredient->name : $item->free_text_name),
                        'quantity' => $item->quantity,
                        'unit' => $item->unit ? [
                            'id' => $item->unit->id,
                            'code' => $item->unit->code,
                            'symbol' => $item->unit->symbol,
                        ] : null,
                        'status' => $item->status,
                        'sort_order' => $item->sort_order,
                        'notes' => $item->notes,
                    ];
                })->values();
            }),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'deleted_at' => optional($this->deleted_at)->toIso8601String(),
        ];
    }
}
