<?php

namespace App\Repositories\Purchases;

use App\Exceptions\Purchases\PurchaseException;
use App\PurchaseItem;

class PurchaseItemRepository
{
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
