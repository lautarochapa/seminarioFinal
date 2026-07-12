<?php

namespace App\Services\ShoppingListCompletion;

use App\AuditLog;
use App\Exceptions\Purchases\PurchaseException;
use App\Purchase;
use App\PurchaseItem;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ShoppingListCompletion\ShoppingListCompletionRepository;
use App\Repositories\ShoppingLists\ShoppingListRepository;
use App\Services\ManualProductStock\ManualProductStockService;
use App\ShoppingList;
use App\ShoppingListItem;
use App\User;
use Illuminate\Support\Facades\DB;

class ShoppingListCompletionService
{
    private $groups;
    private $lists;
    private $repo;
    private $manualProductStock;

    public function __construct(
        FamilyGroupRepository $groups,
        ShoppingListRepository $lists,
        ShoppingListCompletionRepository $repo,
        ManualProductStockService $manualProductStock
    ) {
        $this->groups = $groups;
        $this->lists = $lists;
        $this->repo = $repo;
        $this->manualProductStock = $manualProductStock;
    }

    public function complete(User $user, int $groupId, int $listId, array $data, string $ip, string $ua): array
    {
        $this->groups->findOrFailForUser($groupId, $user->id);
        $list = $this->findList($groupId, $listId);

        if ($list->status === ShoppingList::STATUS_COMPLETED) {
            throw new PurchaseException('SHOPPING_LIST_ALREADY_COMPLETED', 'Esta lista ya fue finalizada.', 409);
        }
        if (!$list->canTransitionTo(ShoppingList::STATUS_COMPLETED)) {
            throw new PurchaseException(
                'SHOPPING_LIST_INVALID_STATUS_TRANSITION',
                'La lista debe estar "En compra" o "Lista para comprar" para poder finalizarse.',
                409
            );
        }

        $defaultLocationId = $data['stock_location_id'] ?? null;
        if ($defaultLocationId !== null && !$this->activeStockLocationExists($groupId, (int) $defaultLocationId)) {
            throw new PurchaseException('STOCK_LOCATION_NOT_FOUND', 'La ubicacion de stock no existe para este grupo.', 422);
        }

        $requestedItems = $this->indexByShoppingListItemId($data['items'] ?? []);

        return DB::transaction(function () use ($user, $groupId, $list, $requestedItems, $defaultLocationId, $ip, $ua) {
            // Re-check under a row lock: two concurrent "Finalizar compra" clicks/retries must not
            // both pass the pre-transaction status check and double-process the same list.
            $locked = ShoppingList::where('id', $list->id)->lockForUpdate()->first();
            if ($locked->status === ShoppingList::STATUS_COMPLETED) {
                throw new PurchaseException('SHOPPING_LIST_ALREADY_COMPLETED', 'Esta lista ya fue finalizada.', 409);
            }

            $purchasedItems = $list->items()->where('status', 'purchased')->lockForUpdate()->get();

            $purchase = Purchase::create([
                'family_group_id' => $groupId,
                'shopping_list_id' => $list->id,
                'user_id' => $user->id,
                'purchase_date' => now()->toDateString(),
                'estimated_total' => 0,
                'actual_total' => 0,
                'status' => 'confirmed',
            ]);

            $stockCreated = 0;
            $stockUpdated = 0;
            $itemsAddedToStock = 0;
            $itemsOmitted = 0;
            $movementsCreated = 0;
            $warnings = [];
            $estimatedTotal = 0.0;
            $actualTotal = 0.0;

            foreach ($purchasedItems as $item) {
                if ($item->stock_processed_at !== null) {
                    // Already processed by a previous run of this same operation: idempotent no-op.
                    continue;
                }

                $estimatedTotal += (float) ($item->estimated_price ?? 0) * (float) $item->quantity;

                $request = $requestedItems[$item->id] ?? null;
                if ($request === null || empty($request['add_to_stock'])) {
                    $itemsOmitted++;
                    $item->stock_processed_at = now();
                    $item->save();
                    continue;
                }

                $resolution = $this->resolveProduct($groupId, $user, $item, $request, $ip, $ua);

                if ($resolution === null) {
                    $itemsOmitted++;
                    $warnings[] = [
                        'shopping_list_item_id' => $item->id,
                        'reason' => empty($item->product_id) && empty($item->ingredient_id)
                            ? 'FREE_TEXT_WITHOUT_PRODUCT_ASSOCIATION'
                            : 'INGREDIENT_WITHOUT_PRODUCT_ASSOCIATION',
                    ];
                    $item->stock_processed_at = now();
                    $item->save();
                    continue;
                }

                [$product, $reusedStockItem, $reusedMovement] = $resolution;

                $quantity = (float) ($request['quantity'] ?? $item->quantity);
                $unitId = (int) ($request['unit_id'] ?? $item->unit_id);

                if ($quantity <= 0) {
                    $itemsOmitted++;
                    $warnings[] = ['shopping_list_item_id' => $item->id, 'reason' => 'ZERO_QUANTITY'];
                    $item->stock_processed_at = now();
                    $item->save();
                    continue;
                }

                if (!$this->repo->activeUnitExists($unitId)) {
                    $itemsOmitted++;
                    $warnings[] = ['shopping_list_item_id' => $item->id, 'reason' => 'UNIT_NOT_FOUND'];
                    $item->stock_processed_at = now();
                    $item->save();
                    continue;
                }

                $actualPrice = $request['actual_price'] ?? $item->actual_price;
                $expirationDate = $request['expiration_date'] ?? null;
                $locationId = $request['stock_location_id'] ?? $defaultLocationId;

                if ($reusedStockItem !== null) {
                    // Product resolution already created/updated its own stock item and movement
                    // (pending-product path, delegated to ManualProductStockService).
                    $stockItem = $reusedStockItem;
                    $reusedMovement ? $stockUpdated++ : $stockCreated++;
                } else {
                    $existing = $this->repo->findCompatibleStock($groupId, $product->id, $unitId, $locationId, $expirationDate);

                    if ($existing) {
                        $existing = $this->repo->updateStockItem($existing, array_filter([
                            'quantity' => (float) $existing->quantity + $quantity,
                            'estimated_purchase_price' => $actualPrice ?? $existing->estimated_purchase_price,
                        ], function ($v) {
                            return $v !== null;
                        }));
                        $stockItem = $existing;
                        $stockUpdated++;
                    } else {
                        $stockItem = $this->repo->createStockItem([
                            'family_group_id' => $groupId,
                            'product_id' => $product->id,
                            'stock_location_id' => $locationId,
                            'quantity' => $quantity,
                            'unit_id' => $unitId,
                            'purchase_date' => now()->toDateString(),
                            'expiration_date' => $expirationDate,
                            'estimated_purchase_price' => $actualPrice,
                            'status' => 'active',
                        ]);
                        $stockCreated++;
                    }

                    $this->repo->createMovement([
                        'family_group_id' => $groupId,
                        'stock_item_id' => $stockItem->id,
                        'product_id' => $product->id,
                        'movement_type' => 'entry',
                        'quantity' => $quantity,
                        'unit_id' => $unitId,
                        'reason' => 'shopping_list_completion',
                        'related_purchase_id' => $purchase->id,
                        'created_by' => $user->id,
                    ]);
                    $movementsCreated++;
                }

                $purchaseItem = PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_id' => $unitId,
                    'unit_price' => $actualPrice,
                    'total_price' => $actualPrice !== null ? (float) $actualPrice * $quantity : null,
                    'expiration_date' => $expirationDate,
                    'created_stock_item_id' => $stockItem->id,
                ]);

                if ($actualPrice !== null) {
                    $actualTotal += (float) $actualPrice * $quantity;
                }

                $item->product_id = $item->product_id ?: $product->id;
                $item->actual_price = $actualPrice ?? $item->actual_price;
                $item->purchase_item_id = $purchaseItem->id;
                $item->stock_processed_at = now();
                $item->save();

                $itemsAddedToStock++;
            }

            $purchase->estimated_total = $estimatedTotal;
            $purchase->actual_total = $actualTotal;
            $purchase->save();

            $list->status = ShoppingList::STATUS_COMPLETED;
            $list->save();

            $this->audit($user->id, 'shopping_list.completed', $list->id, [
                'purchase_id' => $purchase->id,
                'items_purchased_count' => $purchasedItems->count(),
                'items_added_to_stock_count' => $itemsAddedToStock,
                'items_omitted_count' => $itemsOmitted,
                'stock_items_created' => $stockCreated,
                'stock_items_updated' => $stockUpdated,
            ], $ip, $ua);

            return [
                'purchase' => $purchase->fresh(['items.product', 'items.unit']),
                'list' => $list->fresh(['items.ingredient', 'items.product', 'items.unit']),
                'summary' => [
                    'items_purchased_count' => $purchasedItems->count(),
                    'items_added_to_stock_count' => $itemsAddedToStock,
                    'items_omitted_count' => $itemsOmitted,
                    'stock_items_created' => $stockCreated,
                    'stock_items_updated' => $stockUpdated,
                    'stock_movements_created' => $movementsCreated,
                    'warnings' => $warnings,
                ],
            ];
        });
    }

    /**
     * Resolves the product to stock for one shopping list item. Returns null when the item
     * cannot be safely resolved (no auto-guessing of product/ingredient associations — the
     * caller must have confirmed one explicitly). When the pending-product path is used, the
     * stock item/movement it created is returned so the caller doesn't duplicate that work.
     */
    private function resolveProduct(int $groupId, User $user, ShoppingListItem $item, array $request, string $ip, string $ua): ?array
    {
        if (!empty($item->product_id)) {
            $product = $this->repo->usableProduct($groupId, (int) $item->product_id);

            return $product ? [$product, null, null] : null;
        }

        if (!empty($request['product_id'])) {
            $product = $this->repo->usableProduct($groupId, (int) $request['product_id']);

            return $product ? [$product, null, null] : null;
        }

        if (!empty($request['create_pending_product'])) {
            $name = $request['name'] ?? $item->free_text_name;
            if (!$name) {
                return null;
            }

            $result = $this->manualProductStock->create($groupId, $user, [
                'product' => array_filter([
                    'name' => $name,
                    'brand' => $request['brand'] ?? null,
                    'presentation' => $request['presentation'] ?? null,
                    'unit_id' => $request['unit_id'] ?? $item->unit_id,
                    'ingredient_id' => $item->ingredient_id,
                ], function ($v) {
                    return $v !== null;
                }),
                'stock' => array_filter([
                    'quantity' => $request['quantity'] ?? $item->quantity,
                    'unit_id' => $request['unit_id'] ?? $item->unit_id,
                    'stock_location_id' => $request['stock_location_id'] ?? null,
                    'expiration_date' => $request['expiration_date'] ?? null,
                    'purchase_price' => $request['actual_price'] ?? null,
                ], function ($v) {
                    return $v !== null;
                }),
            ], $ip, $ua);

            return [$result['product'], $result['stock_item'], $result['status'] === 200];
        }

        return null;
    }

    private function indexByShoppingListItemId(array $items): array
    {
        $indexed = [];
        foreach ($items as $entry) {
            if (isset($entry['shopping_list_item_id'])) {
                $indexed[(int) $entry['shopping_list_item_id']] = $entry;
            }
        }

        return $indexed;
    }

    private function findList(int $groupId, int $listId): ShoppingList
    {
        $list = $this->lists->findInGroup($groupId, $listId);
        if (!$list) {
            throw new PurchaseException('SHOPPING_LIST_NOT_FOUND', 'La lista de compras no existe.', 404);
        }

        return $list;
    }

    private function activeStockLocationExists(int $groupId, int $locationId): bool
    {
        return \App\StockLocation::where('id', $locationId)
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
    }

    private function audit(int $userId, string $action, int $listId, array $new, string $ip, string $ua): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_name' => 'shopping_lists',
            'entity_id' => (string) $listId,
            'old_values' => null,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }
}
