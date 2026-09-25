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
use App\Services\ShoppingLists\ShoppingListTotalService;
use App\ShoppingList;
use App\ShoppingListItem;
use App\User;
use App\UnitMeasure;
use Illuminate\Support\Facades\DB;

class ShoppingAlternativeService
{
    private $groups;
    private $lists;
    private $items;
    private $alternatives;
    private $packaging;
    private $conversions;
    private $totals;

    public function __construct(
        FamilyGroupRepository $groups,
        ShoppingListRepository $lists,
        ShoppingListItemRepository $items,
        ShoppingAlternativeRepository $alternatives,
        ProductPackagingService $packaging,
        RecipeAvailabilityRepository $conversions,
        ShoppingListTotalService $totals
    ) {
        $this->groups = $groups;
        $this->lists = $lists;
        $this->items = $items;
        $this->alternatives = $alternatives;
        $this->packaging = $packaging;
        $this->conversions = $conversions;
        $this->totals = $totals;
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
                'status' => $item->status,
                'list_status' => $list->status,
                'can_select' => $this->canSelect($list, $item),
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

    public function select(User $user, int $groupId, int $listId, int $itemId, ?int $alternativeId, string $ip, string $ua, ?int $supermarketProductId = null): ShoppingListItem
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);
        $this->findItem($list->id, $itemId);

        return DB::transaction(function () use ($user, $list, $itemId, $alternativeId, $supermarketProductId, $ip, $ua) {
            // Serialize with completion before changing any product, quantity or price.
            $list = ShoppingList::where('id', $list->id)->lockForUpdate()->firstOrFail();
            $item = ShoppingListItem::where('shopping_list_id', $list->id)->where('id', $itemId)->lockForUpdate()->firstOrFail();
            if (!$this->canSelect($list, $item)) {
                throw new FamilyGroupException('SHOPPING_ALTERNATIVE_NOT_EDITABLE', 'Solo podes cambiar alternativas de items pendientes en una lista abierta.', 409);
            }

            $alternative = $alternativeId ? $this->alternatives->findAlternativeForItem($item->id, $alternativeId) : null;
            if ($alternativeId && !$alternative) {
                throw new FamilyGroupException('SHOPPING_ALTERNATIVE_NOT_FOUND', 'La alternativa no existe para este item.', 404);
            }
            $offerId = $alternative ? $alternative->supermarket_product_id : $supermarketProductId;
            $offer = $this->candidateProducts($item, true)->firstWhere('id', $offerId);
            $price = $offer ? $offer->prices->first() : null;
            if (!$offer || !$price || ($alternative && (int) $alternative->product_id !== (int) $offer->product_id)) {
                throw new FamilyGroupException('SHOPPING_ALTERNATIVE_NOT_FOUND', 'La alternativa ya no esta disponible para este item.', 404);
            }
            $purchase = $this->alternativePurchase($item, $offer->product);
            if ($purchase === null) {
                throw new FamilyGroupException('SHOPPING_ALTERNATIVE_INCOMPATIBLE', 'No se puede conservar el contenido con la presentacion de esta alternativa.', 422);
            }

            $old = $this->payload($item);
            $ingredientId = $item->ingredient_id ?: optional($item->product)->ingredient_id;
            $reason = $this->reason($item, (int) $offer->product->ingredient_id, (int) $ingredientId, $price->price, $purchase['quantity']);
            $alternative = $alternative ?: $this->alternatives->selectionForOffer($item->id, $offer->id);
            $this->alternatives->clearSelected($item->id);
            if ($alternative->exists) $alternative->refresh();
            $alternative->fill([
                'product_id' => $offer->product_id,
                'supermarket_product_id' => $offer->id,
                'price' => $price->price,
                'reason' => $reason,
                'is_selected' => true,
            ])->save();

            if ((int) $item->product_id !== (int) $offer->product_id || (int) $item->unit_id !== $purchase['unit_id']) {
                $item->actual_price = null;
            }
            $item->product_id = $offer->product_id;
            $item->quantity = $purchase['quantity'];
            $item->unit_id = $purchase['unit_id'];
            $item->selected_supermarket_product_id = $offer->id;
            $item->estimated_price = $price->price;
            $item->price_source = 'supermarket';
            $item->price_updated_at = $price->scraped_at;
            $item->supermarket_chain_id = $offer->supermarket_chain_id;
            $item->supermarket_branch_id = $offer->supermarket_branch_id;
            $item->save();
            $this->totals->recalculate($list);
            $item = $item->fresh(['ingredient', 'product', 'unit']);

            $this->audit($user->id, 'shopping_list_item.alternative_selected', $item->id, $old, $this->payload($item), $ip, $ua);
            return $item;
        });
    }

    private function canSelect(ShoppingList $list, ShoppingListItem $item): bool
    {
        return in_array($list->status, ['draft', 'active', 'in_progress'], true)
            && $item->status === 'pending' && !$item->purchase_item_id && !$item->stock_processed_at;
    }

    private function candidateProducts(ShoppingListItem $item, bool $includeCurrent = false)
    {
        $ingredientId = $item->ingredient_id ?: optional($item->product)->ingredient_id;
        if (!$ingredientId || !$item->unit_id) return collect();
        $packageCount = $this->packaging->isPackageUnit((int) $item->unit_id);
        // Package substitutions preserve the same food, not an inferred food-equivalence ratio.
        $ingredientIds = $packageCount ? [(int) $ingredientId] : $this->alternatives->compatibleIngredientIds((int) $ingredientId);
        return $this->alternatives->productsForIngredients($ingredientIds, (int) $item->unit_id,
            !$includeCurrent && $item->product_id ? (int) $item->product_id : null, $packageCount);
    }

    private function alternativesForItem(ShoppingListItem $item): array
    {
        $ingredientId = $item->ingredient_id ?: optional($item->product)->ingredient_id;
        if (!$ingredientId || !$item->unit_id) {
            return [];
        }

        $products = $this->candidateProducts($item);
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
                    'net_quantity' => $product->net_quantity,
                    'package_unit' => $this->unitPayload($product->packageUnit),
                ],
                'supermarket_product_id' => $supermarketProduct->id,
                'price' => $price->price,
                'unit_price' => $price->unit_price,
                'purchase_quantity' => $purchase['quantity'],
                'purchase_unit_id' => $purchase['unit_id'],
                'purchase_unit' => $this->unitPayload(UnitMeasure::find($purchase['unit_id'])),
                'estimated_subtotal' => round((float) $price->price * $purchase['quantity'], 2),
                'reason' => $this->reason($item, $product->ingredient_id, $ingredientId, $price->price, $purchase['quantity']),
            ];
        }

        usort($data, function ($left, $right) {
            return (float) $left['estimated_subtotal'] <=> (float) $right['estimated_subtotal'];
        });

        return array_values($data);
    }

    private function unitPayload($unit): ?array
    {
        return $unit ? ['id' => $unit->id, 'code' => $unit->code, 'symbol' => $unit->symbol] : null;
    }

    private function alternativePurchase(ShoppingListItem $item, Product $product): ?array
    {
        if (!$this->packaging->isPackageUnit((int) $item->unit_id)) {
            if (!$this->packaging->hasContent($product)) {
                if ((float) $product->net_quantity > 0 || (int) $product->default_unit_id !== (int) $item->unit_id) {
                    return null;
                }
                return ['quantity' => (float) $item->quantity, 'unit_id' => (int) $item->unit_id];
            }
            // A supermarket price describes the whole package, never each gram of its content.
            $ingredientId = $item->ingredient_id ?: optional($item->product)->ingredient_id;
            $unitId = $this->packaging->purchaseUnitId($product);
            $factor = $this->conversions->findConversionFactor((int) $item->unit_id, (int) $product->package_unit_id, (int) $ingredientId);
            if (!$unitId || $factor === null || $factor <= 0 || (float) $item->quantity <= 0) {
                return null;
            }
            $packages = max(1, (int) ceil(round((float) $item->quantity * $factor / (float) $product->net_quantity, 8)));
            return ['quantity' => $packages, 'unit_id' => $unitId];
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
