<?php

namespace App\Services\ShoppingLists;

use App\ShoppingList;
use Illuminate\Support\Facades\DB;

class ShoppingListTotalService
{
    /**
     * Call inside the item mutation transaction after locking the parent list.
     * Sum known line subtotals as shown by ShoppingListItemResource; unknown prices
     * remain null on the items. Empty lists and no known prices contribute zero.
     */
    public function recalculate(ShoppingList $list): void
    {
        $list->estimated_total = $list->items()->whereNotIn('status', ['skipped', 'cancelled'])
            ->sum(DB::raw('ROUND(COALESCE(quantity, 0) * COALESCE(estimated_price, 0), 2)'));
        $list->save();
    }
}
