<?php

namespace App\Repositories\ShoppingListPreview;

use App\MealPlan;
use App\ShoppingList;
use App\ShoppingListItem;
use App\StockItem;
use App\UnitConversion;

class ShoppingListPreviewRepository
{
    public function findPlanForGroup(int $planId, int $groupId): ?MealPlan
    {
        return MealPlan::with([
            'items' => function ($query) {
                $query->whereNull('deleted_at')
                    ->whereNotIn('status', ['skipped', 'eating_out', 'cancelled'])
                    ->where('is_eating_out', false)
                    ->with(['recipe.ingredients.ingredient', 'recipe.ingredients.unit']);
            },
        ])
            ->where('id', $planId)
            ->where('family_group_id', $groupId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function stockItemsForIngredient(int $groupId, int $ingredientId): array
    {
        return StockItem::query()
            ->join('products as p', 'p.id', '=', 'stock_items.product_id')
            ->where('stock_items.family_group_id', $groupId)
            ->where('stock_items.status', 'active')
            ->whereNull('stock_items.deleted_at')
            ->where('stock_items.quantity', '>', 0)
            ->where('p.ingredient_id', $ingredientId)
            ->orderBy('stock_items.expiration_date')
            ->orderBy('stock_items.id')
            ->select([
                'stock_items.id',
                'stock_items.quantity',
                'stock_items.unit_id',
                'stock_items.product_id',
            ])
            ->get()
            ->toArray();
    }

    public function conversionFactor(int $fromUnitId, int $toUnitId, int $ingredientId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return 1.0;
        }

        $conversion = UnitConversion::where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->where('status', 'active')
            ->where(function ($query) use ($ingredientId) {
                $query->where('ingredient_id', $ingredientId)->orWhereNull('ingredient_id');
            })
            ->orderByRaw('CASE WHEN ingredient_id IS NOT NULL THEN 0 ELSE 1 END')
            ->first();

        return $conversion ? (float) $conversion->factor : null;
    }

    public function existingList(int $groupId, int $planId): ?ShoppingList
    {
        return ShoppingList::with(['items.ingredient', 'items.unit'])
            ->where('family_group_id', $groupId)
            ->where('meal_plan_id', $planId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function createList(array $data): ShoppingList
    {
        return ShoppingList::create($data);
    }

    public function replaceItems(ShoppingList $list, array $items): void
    {
        ShoppingListItem::where('shopping_list_id', $list->id)->delete();

        foreach ($items as $item) {
            ShoppingListItem::create([
                'shopping_list_id' => $list->id,
                'ingredient_id' => $item['ingredient']['id'],
                'product_id' => null,
                'quantity' => $item['missing_quantity'],
                'unit_id' => $item['unit']['id'],
                'status' => 'pending',
                'notes' => $this->notes($item),
            ]);
        }
    }

    public function loadList(ShoppingList $list): ShoppingList
    {
        return $list->load(['items.ingredient', 'items.unit']);
    }

    private function notes(array $item): ?string
    {
        if (empty($item['incomplete'])) {
            return null;
        }

        return 'Datos incompletos o unidades incompatibles en el calculo.';
    }
}
