<?php

namespace App\Repositories\ShoppingListCompletion;

use App\Product;
use App\StockItem;
use App\StockMovement;
use App\UnitMeasure;

class ShoppingListCompletionRepository
{
    public function activeUnitExists(int $unitId): bool
    {
        return UnitMeasure::where('id', $unitId)->where('status', 'active')->exists();
    }

    public function usableProduct(int $groupId, int $productId): ?Product
    {
        return Product::where('id', $productId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($groupId) {
                $q->where('status', 'active')
                    ->orWhere(function ($q2) use ($groupId) {
                        $q2->where('status', 'pending_review')->where('family_group_id', $groupId);
                    });
            })
            ->first();
    }

    public function findCompatibleStock(int $groupId, int $productId, int $unitId, ?int $locationId, ?string $expirationDate)
    {
        return StockItem::where('family_group_id', $groupId)
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($locationId) {
                $locationId === null ? $q->whereNull('stock_location_id') : $q->where('stock_location_id', $locationId);
            })
            ->where(function ($q) use ($expirationDate) {
                $expirationDate === null ? $q->whereNull('expiration_date') : $q->whereDate('expiration_date', $expirationDate);
            })
            ->first();
    }

    public function compatibleProductsForIngredient(int $groupId, int $ingredientId, int $unitId)
    {
        return Product::where('ingredient_id', $ingredientId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($groupId) {
                $q->where(function ($active) {
                    $active->where('status', 'active')->whereNull('family_group_id');
                })->orWhere(function ($pending) use ($groupId) {
                    $pending->where('status', 'pending_review')->where('family_group_id', $groupId);
                });
            })
            ->where(function ($q) use ($unitId) {
                $q->where('default_unit_id', $unitId)->orWhere('package_unit_id', $unitId);
            })
            ->orderByRaw("CASE WHEN status = 'pending_review' THEN 0 ELSE 1 END")
            ->get();
    }

    public function createStockItem(array $data): StockItem
    {
        return StockItem::create($data);
    }

    public function updateStockItem(StockItem $item, array $data): StockItem
    {
        $item->fill($data);
        $item->save();

        return $item;
    }

    public function createMovement(array $data): StockMovement
    {
        return StockMovement::create($data);
    }
}
