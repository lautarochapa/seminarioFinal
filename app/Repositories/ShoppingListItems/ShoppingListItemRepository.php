<?php

namespace App\Repositories\ShoppingListItems;

use App\Ingredient;
use App\Product;
use App\ShoppingListItem;
use App\UnitMeasure;
use Illuminate\Support\Collection;

class ShoppingListItemRepository
{
    public function listForShoppingList(int $shoppingListId): Collection
    {
        return ShoppingListItem::with(['ingredient', 'product', 'unit'])
            ->where('shopping_list_id', $shoppingListId)
            ->orderBy('id')
            ->get();
    }

    public function findInShoppingList(int $shoppingListId, int $itemId): ?ShoppingListItem
    {
        return ShoppingListItem::with(['ingredient', 'product', 'unit'])
            ->where('shopping_list_id', $shoppingListId)
            ->where('id', $itemId)
            ->first();
    }

    public function create(array $data): ShoppingListItem
    {
        return ShoppingListItem::create($data)->load(['ingredient', 'product', 'unit']);
    }

    public function update(ShoppingListItem $item, array $data): ShoppingListItem
    {
        $item->fill($data);
        $item->save();

        return $item->fresh(['ingredient', 'product', 'unit']);
    }

    public function delete(ShoppingListItem $item): void
    {
        $item->delete();
    }

    public function duplicateExists(int $shoppingListId, ?int $ingredientId, ?int $productId, int $unitId, ?int $exceptId = null): bool
    {
        $query = ShoppingListItem::where('shopping_list_id', $shoppingListId)
            ->where('unit_id', $unitId);

        if ($productId !== null) {
            $query->where('product_id', $productId);
        } else {
            $query->whereNull('product_id')->where('ingredient_id', $ingredientId);
        }

        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function activeUnitExists(int $unitId): bool
    {
        return UnitMeasure::where('id', $unitId)
            ->where('status', 'active')
            ->exists();
    }

    public function activeIngredientExists(int $ingredientId): bool
    {
        return Ingredient::where('id', $ingredientId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
    }

    public function activeProductExists(int $productId): bool
    {
        return Product::where('id', $productId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
    }
}
