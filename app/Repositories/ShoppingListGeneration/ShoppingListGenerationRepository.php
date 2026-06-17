<?php

namespace App\Repositories\ShoppingListGeneration;

use App\PurchaseItem;
use App\ShoppingList;
use App\ShoppingListItem;

class ShoppingListGenerationRepository
{
    public function historyRows(int $groupId, array $filters): array
    {
        $query = PurchaseItem::query()
            ->join('purchases as p', 'p.id', '=', 'purchase_items.purchase_id')
            ->join('products as pr', 'pr.id', '=', 'purchase_items.product_id')
            ->where('p.family_group_id', $groupId)
            ->whereNull('p.deleted_at')
            ->where('p.status', 'confirmed')
            ->whereNotNull('pr.ingredient_id')
            ->select([
                'pr.ingredient_id',
                'purchase_items.unit_id',
                'purchase_items.quantity',
            ]);

        if (!empty($filters['date_from'])) {
            $query->whereDate('p.purchase_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('p.purchase_date', '<=', $filters['date_to']);
        }

        return $query->get()->toArray();
    }

    public function existingHistoryList(int $groupId): ?ShoppingList
    {
        return ShoppingList::with(['items.ingredient', 'items.unit'])
            ->where('family_group_id', $groupId)
            ->where('source_type', 'history')
            ->whereNull('meal_plan_id')
            ->whereNull('deleted_at')
            ->first();
    }

    public function createHistoryList(int $groupId, int $userId): ShoppingList
    {
        return ShoppingList::create([
            'family_group_id' => $groupId,
            'meal_plan_id' => null,
            'created_by' => $userId,
            'source_type' => 'history',
            'status' => 'draft',
        ]);
    }

    public function replaceItems(ShoppingList $list, array $items): void
    {
        ShoppingListItem::where('shopping_list_id', $list->id)->delete();

        foreach ($items as $item) {
            ShoppingListItem::create([
                'shopping_list_id' => $list->id,
                'ingredient_id' => $item['ingredient_id'],
                'product_id' => null,
                'quantity' => $item['quantity'],
                'unit_id' => $item['unit_id'],
                'status' => 'pending',
            ]);
        }
    }

    public function loadList(ShoppingList $list): ShoppingList
    {
        return $list->load(['items.ingredient', 'items.unit']);
    }
}
