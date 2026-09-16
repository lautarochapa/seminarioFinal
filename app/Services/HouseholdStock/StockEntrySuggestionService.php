<?php

namespace App\Services\HouseholdStock;

use App\Product;
use App\StockItem;

class StockEntrySuggestionService
{
    public function forProduct(Product $product, int $groupId): array
    {
        $items = $product->relationLoaded('stockItems')
            ? $product->stockItems
            : StockItem::with('unit')
                ->where('family_group_id', $groupId)
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->get();

        $units = $items->filter(function ($item) {
            return $item->unit_id && $item->unit;
        })->unique('unit_id')->map(function ($item) {
            return [
                'id' => (int) $item->unit_id,
                'code' => $item->unit->code,
                'name' => $item->unit->name,
                'symbol' => $item->unit->symbol,
            ];
        })->values();

        $unitId = null;
        $quantity = null;
        $source = null;

        if ($units->count() === 1) {
            $unitId = (int) $units->first()['id'];
            $source = 'existing_stock';
            if ($product->net_quantity && (int) $product->package_unit_id === $unitId) {
                $quantity = (float) $product->net_quantity;
            }
        } elseif ($units->count() === 0 && $product->net_quantity && $product->package_unit_id) {
            $unitId = (int) $product->package_unit_id;
            $quantity = (float) $product->net_quantity;
            $source = 'package';
        } elseif ($units->count() === 0 && $product->default_unit_id) {
            $unitId = (int) $product->default_unit_id;
            $source = 'default_unit';
        }

        return [
            'quantity' => $quantity,
            'unit_id' => $unitId,
            'source' => $source,
            'requires_unit_selection' => $unitId === null,
            'existing_units' => $units->all(),
        ];
    }
}
