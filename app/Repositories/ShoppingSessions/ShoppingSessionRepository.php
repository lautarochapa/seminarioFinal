<?php

namespace App\Repositories\ShoppingSessions;

use App\ProductBarcode;
use App\ShoppingSession;
use App\ShoppingSessionScan;

class ShoppingSessionRepository
{
    public function activeForListAndUser(int $listId, int $userId): ?ShoppingSession
    {
        return ShoppingSession::where('shopping_list_id', $listId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }

    public function create(array $data): ShoppingSession
    {
        return ShoppingSession::create($data)->load(['shoppingList', 'branch']);
    }

    public function findInGroup(int $groupId, int $sessionId): ?ShoppingSession
    {
        return ShoppingSession::with(['shoppingList.items.product', 'shoppingList.items.unit', 'scans'])
            ->where('family_group_id', $groupId)
            ->where('id', $sessionId)
            ->first();
    }

    public function productByBarcode(string $barcode)
    {
        $record = ProductBarcode::with('product')
            ->where('barcode', $barcode)
            ->where('status', 'active')
            ->first();

        if (!$record || !$record->product || $record->product->status !== 'active' || !$record->product->is_active || $record->product->deleted_at !== null) {
            return null;
        }

        return $record->product;
    }

    public function scanExists(int $sessionId, int $productId): bool
    {
        return ShoppingSessionScan::where('shopping_session_id', $sessionId)
            ->where('product_id', $productId)
            ->exists();
    }

    public function createScan(array $data): ShoppingSessionScan
    {
        return ShoppingSessionScan::create($data)->load(['product', 'shoppingListItem']);
    }
}
