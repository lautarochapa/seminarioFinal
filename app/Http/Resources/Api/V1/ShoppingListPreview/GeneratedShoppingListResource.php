<?php

namespace App\Http\Resources\Api\V1\ShoppingListPreview;

use Illuminate\Http\Resources\Json\JsonResource;

class GeneratedShoppingListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'family_group_id' => $this->family_group_id,
            'meal_plan_id' => $this->meal_plan_id,
            'source_type' => $this->source_type,
            'status' => $this->status,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'ingredient' => $item->ingredient ? [
                            'id' => $item->ingredient->id,
                            'name' => $item->ingredient->name,
                        ] : null,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit ? [
                            'id' => $item->unit->id,
                            'code' => $item->unit->code,
                            'symbol' => $item->unit->symbol,
                        ] : null,
                        'status' => $item->status,
                        'notes' => $item->notes,
                    ];
                })->values();
            }),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
