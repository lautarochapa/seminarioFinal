<?php

namespace App\Repositories\HouseholdStock;

use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Product;
use App\StockItem;
use App\StockLocation;
use App\UnitMeasure;

class HouseholdStockRepository
{
    public function paginateForGroup(int $groupId, array $filters)
    {
        $query = StockItem::with(['product', 'location', 'unit'])
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at');

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['stock_location_id'])) {
            $query->where('stock_location_id', (int) $filters['stock_location_id']);
        }

        if (! empty($filters['expires_before'])) {
            $query->whereDate('expiration_date', '<=', $filters['expires_before']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderByRaw('expiration_date is null')
            ->orderBy('expiration_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findInGroupOrFail(int $groupId, int $stockItemId): StockItem
    {
        $item = StockItem::with(['product', 'location', 'unit'])
            ->where('family_group_id', $groupId)
            ->where('id', $stockItemId)
            ->first();

        if (! $item) {
            throw new FamilyGroupException('STOCK_ITEM_NOT_FOUND', 'Item de stock no encontrado.', 404);
        }

        return $item;
    }

    public function findDuplicate(int $groupId, int $productId, ?int $locationId): ?StockItem
    {
        return StockItem::where('family_group_id', $groupId)
            ->where('product_id', $productId)
            ->where('stock_location_id', $locationId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
    }

    public function create(array $data): StockItem
    {
        return StockItem::create($data);
    }

    public function update(StockItem $item, array $data): StockItem
    {
        $item->fill($data);
        $item->save();

        return $item->fresh(['product', 'location', 'unit']);
    }

    public function delete(StockItem $item): StockItem
    {
        $item->delete();

        return StockItem::withTrashed()->with(['product', 'location', 'unit'])->find($item->id);
    }

    public function activeProductExists(int $productId, ?int $groupId = null): bool
    {
        return Product::where('id', $productId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($groupId) {
                $query->where('status', 'active');

                if ($groupId) {
                    $query->orWhere(function ($pending) use ($groupId) {
                        $pending->where('status', 'pending_review')
                            ->where('origin', 'user_created')
                            ->where('family_group_id', $groupId);
                    });
                }
            })
            ->exists();
    }

    public function activeLocationInGroupExists(int $groupId, int $locationId): bool
    {
        return StockLocation::where('id', $locationId)
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
    }

    public function activeUnitExists(int $unitId): bool
    {
        return UnitMeasure::where('id', $unitId)
            ->where('status', 'active')
            ->exists();
    }

    public function activeItemsForGroup(int $groupId)
    {
        return StockItem::with('location')
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get();
    }
}
