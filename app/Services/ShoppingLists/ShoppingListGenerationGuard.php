<?php

namespace App\Services\ShoppingLists;

use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\ShoppingList;

class ShoppingListGenerationGuard
{
    /** Recheck children after the caller has acquired the parent list lock. */
    public function assertReusable(ShoppingList $list): void
    {
        $protectedItems = $list->items()->where(function ($query) {
            $query->where('status', 'purchased')
                ->orWhereNotNull('purchase_item_id')
                ->orWhereNotNull('stock_processed_at');
        })->exists();

        if (!in_array($list->status, [ShoppingList::STATUS_DRAFT, ShoppingList::STATUS_ACTIVE], true)
            || $protectedItems || $list->purchases()->withTrashed()->exists()) {
            throw new FamilyGroupException(
                'SHOPPING_LIST_GENERATION_UNSAFE',
                'La lista contiene compras o articulos procesados. Usa otra lista o termina la compra antes de generar.',
                409
            );
        }
    }
}
