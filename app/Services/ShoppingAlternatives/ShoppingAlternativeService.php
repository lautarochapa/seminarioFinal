<?php

namespace App\Services\ShoppingAlternatives;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ShoppingAlternatives\ShoppingAlternativeRepository;
use App\Repositories\ShoppingListItems\ShoppingListItemRepository;
use App\Repositories\ShoppingLists\ShoppingListRepository;
use App\ShoppingList;
use App\ShoppingListItem;
use App\User;
use Illuminate\Support\Facades\DB;

class ShoppingAlternativeService
{
    private $groups;
    private $lists;
    private $items;
    private $alternatives;

    public function __construct(
        FamilyGroupRepository $groups,
        ShoppingListRepository $lists,
        ShoppingListItemRepository $items,
        ShoppingAlternativeRepository $alternatives
    ) {
        $this->groups = $groups;
        $this->lists = $lists;
        $this->items = $items;
        $this->alternatives = $alternatives;
    }

    public function list(User $user, int $groupId, int $listId): array
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);
        $items = $this->items->listForShoppingList($list->id);
        $data = [];

        foreach ($items as $item) {
            $data[] = [
                'item_id' => $item->id,
                'ingredient' => $item->ingredient ? [
                    'id' => $item->ingredient->id,
                    'name' => $item->ingredient->name,
                ] : null,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                ] : null,
                'quantity' => $item->quantity,
                'unit' => $item->unit ? [
                    'id' => $item->unit->id,
                    'code' => $item->unit->code,
                    'symbol' => $item->unit->symbol,
                ] : null,
                'alternatives' => $this->alternativesForItem($item),
            ];
        }

        return $data;
    }

    public function select(User $user, int $groupId, int $listId, int $itemId, int $alternativeId, string $ip, string $ua): ShoppingListItem
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);
        $item = $this->findItem($list->id, $itemId);
        $alternative = $this->alternatives->findAlternativeForItem($item->id, $alternativeId);

        if (!$alternative || !$alternative->product || $alternative->product->status !== 'active' || !$alternative->product->is_active || $alternative->product->deleted_at !== null) {
            throw new FamilyGroupException('SHOPPING_ALTERNATIVE_NOT_FOUND', 'La alternativa no existe para este item.', 404);
        }

        return DB::transaction(function () use ($user, $item, $alternative, $ip, $ua) {
            $old = $this->payload($item);
            $this->alternatives->clearSelected($item->id);
            $alternative->is_selected = true;
            $alternative->save();

            $item->product_id = $alternative->product_id;
            $item->selected_supermarket_product_id = $alternative->supermarket_product_id;
            $item->estimated_price = $alternative->price;
            $item->save();
            $item = $item->fresh(['ingredient', 'product', 'unit']);

            $this->audit($user->id, 'shopping_list_item.alternative_selected', $item->id, $old, $this->payload($item), $ip, $ua);

            return $item;
        });
    }

    private function alternativesForItem(ShoppingListItem $item): array
    {
        $ingredientId = $item->ingredient_id ?: optional($item->product)->ingredient_id;
        if (!$ingredientId || !$item->unit_id) {
            return [];
        }

        $ingredientIds = $this->alternatives->compatibleIngredientIds((int) $ingredientId);
        $products = $this->alternatives->productsForIngredients($ingredientIds, (int) $item->unit_id, $item->product_id ? (int) $item->product_id : null);
        $seen = [];
        $data = [];

        foreach ($products as $supermarketProduct) {
            $product = $supermarketProduct->product;
            if (!$product || isset($seen[$product->id])) {
                continue;
            }

            $price = $supermarketProduct->prices->first();
            if (!$price) {
                continue;
            }

            $seen[$product->id] = true;
            $data[] = [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                ],
                'supermarket_product_id' => $supermarketProduct->id,
                'price' => $price->price,
                'unit_price' => $price->unit_price,
                'reason' => $this->reason($item, $product->ingredient_id, $ingredientId, $price->price),
            ];
        }

        usort($data, function ($left, $right) {
            return (float) $left['price'] <=> (float) $right['price'];
        });

        return array_values($data);
    }

    private function reason(ShoppingListItem $item, int $productIngredientId, int $itemIngredientId, $price): string
    {
        if ($item->estimated_price !== null && (float) $price < (float) $item->estimated_price) {
            return 'cheaper';
        }

        return $productIngredientId === $itemIngredientId ? 'equivalent' : 'equivalent';
    }

    private function assertMember(User $user, int $groupId): void
    {
        $this->groups->findOrFailForUser($groupId, $user->id);
    }

    private function findList(int $groupId, int $listId): ShoppingList
    {
        $list = $this->lists->findInGroup($groupId, $listId);
        if (!$list) {
            throw new FamilyGroupException('SHOPPING_LIST_NOT_FOUND', 'La lista de compras no existe.', 404);
        }

        return $list;
    }

    private function findItem(int $listId, int $itemId): ShoppingListItem
    {
        $item = $this->items->findInShoppingList($listId, $itemId);
        if (!$item) {
            throw new FamilyGroupException('SHOPPING_LIST_ITEM_NOT_FOUND', 'El item de la lista no existe.', 404);
        }

        return $item;
    }

    private function payload(ShoppingListItem $item): array
    {
        return [
            'shopping_list_id' => $item->shopping_list_id,
            'ingredient_id' => $item->ingredient_id,
            'product_id' => $item->product_id,
            'selected_supermarket_product_id' => $item->selected_supermarket_product_id,
            'quantity' => $item->quantity,
            'unit_id' => $item->unit_id,
            'estimated_price' => $item->estimated_price,
            'actual_price' => $item->actual_price,
            'status' => $item->status,
            'notes' => $item->notes,
        ];
    }

    private function audit(int $userId, string $action, int $itemId, ?array $old, array $new, string $ip, string $ua): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_name' => 'shopping_list_items',
            'entity_id' => (string) $itemId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }
}
