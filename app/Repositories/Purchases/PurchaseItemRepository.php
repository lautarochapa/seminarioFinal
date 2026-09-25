<?php

namespace App\Repositories\Purchases;

use App\Exceptions\Purchases\PurchaseException;
use App\PurchaseItem;
use App\ShoppingListItem;

class PurchaseItemRepository
{
    public function lastPriceForProductInGroup(int $productId, int $groupId): ?array
    {
        $row = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->leftJoin('shopping_lists', 'shopping_lists.id', '=', 'purchases.shopping_list_id')
            ->where('purchases.family_group_id', $groupId)
            ->where('purchase_items.product_id', $productId)
            ->whereNotNull('purchase_items.unit_price')
            ->whereNull('purchases.deleted_at')
            ->orderByDesc('purchases.purchase_date')
            ->orderByDesc('purchase_items.id')
            ->select(['purchase_items.id', 'purchase_items.unit_price', 'purchase_items.unit_id', 'purchases.purchase_date', 'purchases.shopping_list_id', 'shopping_lists.source_type as list_source_type', 'shopping_lists.meal_plan_id'])
            ->first();

        if (! $row) {
            return null;
        }

        // Older generated lists stored package counts/prices under physical units.
        // Read provenance (including soft-deleted lists); never reinterpret or rewrite those rows.
        $generatedOrUnknownList = $row->shopping_list_id !== null && (
            $row->list_source_type !== 'manual'
            || $row->meal_plan_id !== null
            || ShoppingListItem::where('shopping_list_id', $row->shopping_list_id)
                ->where('source_type', 'recipe_generation')
                ->where(function ($query) use ($row, $productId) {
                    $query->where('purchase_item_id', $row->id)->orWhere('product_id', $productId);
                })->exists()
        );

        return [
            'price' => (float) $row->unit_price,
            'unit_id' => (int) $row->unit_id,
            'updated_at' => (string) $row->purchase_date,
            'generated_or_unknown_list_source' => $generatedOrUnknownList,
        ];
    }

    public function listForPurchase(int $purchaseId)
    {
        return PurchaseItem::where('purchase_id', $purchaseId)
            ->with(['product', 'unit'])
            ->get();
    }

    public function findForPurchase(int $purchaseId, int $itemId): PurchaseItem
    {
        $item = PurchaseItem::where('purchase_id', $purchaseId)
            ->where('id', $itemId)
            ->with(['product', 'unit'])
            ->first();

        if (!$item) {
            throw new PurchaseException('PURCHASE_ITEM_NOT_FOUND', 'El item no existe o no pertenece a la compra.', 404);
        }

        return $item;
    }

    public function create(array $data): PurchaseItem
    {
        return PurchaseItem::create($data);
    }

    public function update(PurchaseItem $item, array $data): PurchaseItem
    {
        $item->update($data);
        return $item->fresh(['product', 'unit']);
    }

    public function delete(PurchaseItem $item): void
    {
        $item->delete();
    }

    public function recalculateTotal(int $purchaseId): ?float
    {
        $total = PurchaseItem::where('purchase_id', $purchaseId)->sum('total_price');
        return $total > 0 ? round((float) $total, 2) : null;
    }
}
