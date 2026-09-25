<?php

namespace App\Services\ShoppingAlternatives;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Product;
use App\Repositories\RecipeAvailability\RecipeAvailabilityRepository;
use App\Services\Products\ProductPackagingService;
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
    private $packaging;
    private $conversions;

    public function __construct(
        FamilyGroupRepository $groups,
        ShoppingListRepository $lists,
        ShoppingListItemRepository $items,
        ShoppingAlternativeRepository $alternatives,
        ProductPackagingService $packaging,
        RecipeAvailabilityRepository $conversions
    ) {
        $this->groups = $groups;
        $this->lists = $lists;
        $this->items = $items;
        $this->alternatives = $alternatives;
        $this->packaging = $packaging;
        $this->conversions = $conversions;
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
            $item = ShoppingListItem::where('id', $item->id)->lockForUpdate()->firstOrFail();
            $purchase = $this->alternativePurchase($item, $alternative->product);
            if ($purchase === null) {
                throw new FamilyGroupException('SHOPPING_ALTERNATIVE_INCOMPATIBLE', 'No se puede conservar el contenido con la presentacion de esta alternativa.', 422);
            }
            $old = $this->payload($item);
            $this->alternatives->clearSelected($item->id);
            $alternative->refresh();
            $alternative->is_selected = true;
            $alternative->save();

            if ($this->packaging->isPackageUnit((int) $item->unit_id) && (int) $item->product_id !== (int) $alternative->product_id) {
                $item->actual_price = null;
            }
            $item->product_id = $alternative->product_id;
            $item->quantity = $purchase['quantity'];
            $item->unit_id = $purchase['unit_id'];
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

        $packageCount = $this->packaging->isPackageUnit((int) $item->unit_id);
        // A package-size replacement preserves the same ingredient. Food substitutions
        // need their own quantity ratio and must not be inferred from an equivalence tag.
        $ingredientIds = $packageCount ? [(int) $ingredientId] : $this->alternatives->compatibleIngredientIds((int) $ingredientId);
        $products = $this->alternatives->productsForIngredients($ingredientIds, (int) $item->unit_id, $item->product_id ? (int) $item->product_id : null, $packageCount);
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
            $purchase = $this->alternativePurchase($item, $product);
            if ($purchase === null) {
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
                'purchase_quantity' => $purchase['quantity'],
                'purchase_unit_id' => $purchase['unit_id'],
                'estimated_subtotal' => round((float) $price->price * $purchase['quantity'], 2),
                'reason' => $this->reason($item, $product->ingredient_id, $ingredientId, $price->price, $purchase['quantity']),
            ];
        }

        usort($data, function ($left, $right) {
            return (float) $left['estimated_subtotal'] <=> (float) $right['estimated_subtotal'];
        });

        return array_values($data);
    }

    private function alternativePurchase(ShoppingListItem $item, Product $product): ?array
    {
        if (!$this->packaging->isPackageUnit((int) $item->unit_id)) {
            return ['quantity' => (float) $item->quantity, 'unit_id' => (int) $item->unit_id];
        }
        $original = $item->product;
        if (!$original || !$original->ingredient_id || (int) $original->ingredient_id !== (int) $product->ingredient_id
            || !$this->packaging->hasContent($original) || !$this->packaging->hasContent($product)) {
            return null;
        }
        $unitId = $this->packaging->purchaseUnitId($product);
        $factor = $this->conversions->findConversionFactor((int) $original->package_unit_id, (int) $product->package_unit_id, (int) $original->ingredient_id);
        if (!$unitId || $factor === null || $factor <= 0 || (float) $item->quantity <= 0) {
            return null;
        }
        $requiredContent = (float) $item->quantity * (float) $original->net_quantity * $factor;
        $packages = max(1, (int) ceil(round($requiredContent / (float) $product->net_quantity, 8)));
        return ['quantity' => $packages, 'unit_id' => $unitId];
    }

    private function reason(ShoppingListItem $item, int $productIngredientId, int $itemIngredientId, $price, float $quantity): string
    {
        if ($item->estimated_price !== null && (float) $price * $quantity < (float) $item->estimated_price * (float) $item->quantity) {
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
