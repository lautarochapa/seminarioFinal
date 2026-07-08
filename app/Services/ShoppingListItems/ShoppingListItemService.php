<?php

namespace App\Services\ShoppingListItems;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ShoppingListItems\ShoppingListItemRepository;
use App\Repositories\ShoppingLists\ShoppingListRepository;
use App\ShoppingList;
use App\ShoppingListItem;
use App\User;
use Illuminate\Support\Collection;

class ShoppingListItemService
{
    private $groups;
    private $lists;
    private $items;

    public function __construct(FamilyGroupRepository $groups, ShoppingListRepository $lists, ShoppingListItemRepository $items)
    {
        $this->groups = $groups;
        $this->lists = $lists;
        $this->items = $items;
    }

    public function list(User $user, int $groupId, int $listId): Collection
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);

        return $this->items->listForShoppingList($list->id);
    }

    public function create(User $user, int $groupId, int $listId, array $data, string $ip, string $ua): ShoppingListItem
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);

        $payload = $this->payloadFromInput($data);
        $payload['shopping_list_id'] = $list->id;
        $payload['status'] = $payload['status'] ?? 'pending';

        $this->validateReferences($payload);
        $this->assertNoDuplicate($list->id, $payload['ingredient_id'] ?? null, $payload['product_id'] ?? null, (int) $payload['unit_id']);

        $item = $this->items->create($payload);
        $this->audit($user->id, 'shopping_list_item.created', $item->id, null, $this->payload($item), $ip, $ua);

        return $item;
    }

    public function update(User $user, int $groupId, int $listId, int $itemId, array $data, string $ip, string $ua): ShoppingListItem
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);
        $item = $this->findItem($list->id, $itemId);

        $old = $this->payload($item);
        $fields = $this->payloadFromInput($data);
        $merged = array_merge($old, $fields);

        $this->validateReferences($merged);
        $this->assertNoDuplicate($list->id, $merged['ingredient_id'] ?? null, $merged['product_id'] ?? null, (int) $merged['unit_id'], $item->id);

        $fields = $this->applyManualPriceInvalidation($item, $fields);

        $updated = $this->items->update($item, $fields);
        $new = $this->payload($updated);

        if ($old !== $new) {
            $this->audit($user->id, 'shopping_list_item.updated', $updated->id, $old, $new, $ip, $ua);
        }

        return $updated;
    }

    public function delete(User $user, int $groupId, int $listId, int $itemId, string $ip, string $ua): void
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);
        $item = $this->findItem($list->id, $itemId);
        $old = $this->payload($item);

        $this->items->delete($item);
        $this->audit($user->id, 'shopping_list_item.deleted', $item->id, $old, ['deleted' => true], $ip, $ua);
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

    /**
     * When the user manually changes the product or unit of an item that carries a
     * generated price estimation, that estimation no longer describes what's being
     * bought (different product/packaging => different price), so it's invalidated
     * rather than left silently wrong. A quantity-only edit keeps the existing
     * per-unit price and source, since the subtotal is always recomputed live from
     * price * quantity (never persisted), so it can't become incoherent.
     */
    private function applyManualPriceInvalidation(ShoppingListItem $item, array $fields): array
    {
        $productChanged = array_key_exists('product_id', $fields) && (int) $fields['product_id'] !== (int) $item->product_id;
        $unitChanged = array_key_exists('unit_id', $fields) && (int) $fields['unit_id'] !== (int) $item->unit_id;

        if (! $productChanged && ! $unitChanged) {
            return $fields;
        }

        if (! array_key_exists('estimated_price', $fields)) {
            $fields['estimated_price'] = null;
        }

        $fields['price_source'] = 'manual';
        $fields['price_updated_at'] = now();
        $fields['supermarket_chain_id'] = null;
        $fields['supermarket_branch_id'] = null;

        return $fields;
    }

    private function payloadFromInput(array $data): array
    {
        $allowed = ['ingredient_id', 'product_id', 'quantity', 'unit_id', 'estimated_price', 'actual_price', 'status', 'notes'];

        return array_intersect_key($data, array_flip($allowed));
    }

    private function validateReferences(array $data): void
    {
        if (empty($data['ingredient_id']) && empty($data['product_id'])) {
            throw new FamilyGroupException('SHOPPING_LIST_ITEM_TARGET_REQUIRED', 'El item debe tener ingrediente o producto.', 422);
        }

        if (empty($data['unit_id']) || !$this->items->activeUnitExists((int) $data['unit_id'])) {
            throw new FamilyGroupException('UNIT_NOT_FOUND', 'La unidad no existe o no esta activa.', 422);
        }

        if (!empty($data['ingredient_id']) && !$this->items->activeIngredientExists((int) $data['ingredient_id'])) {
            throw new FamilyGroupException('INGREDIENT_NOT_FOUND', 'El ingrediente no existe o no esta activo.', 422);
        }

        if (!empty($data['product_id']) && !$this->items->activeProductExists((int) $data['product_id'])) {
            throw new FamilyGroupException('PRODUCT_NOT_FOUND', 'El producto no existe o no esta activo.', 422);
        }
    }

    private function assertNoDuplicate(int $listId, ?int $ingredientId, ?int $productId, int $unitId, ?int $exceptId = null): void
    {
        if ($this->items->duplicateExists($listId, $ingredientId, $productId, $unitId, $exceptId)) {
            throw new FamilyGroupException('SHOPPING_LIST_ITEM_DUPLICATE', 'El item ya existe en la lista.', 409);
        }
    }

    private function payload(ShoppingListItem $item): array
    {
        return [
            'shopping_list_id' => $item->shopping_list_id,
            'ingredient_id' => $item->ingredient_id,
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'unit_id' => $item->unit_id,
            'estimated_price' => $item->estimated_price,
            'actual_price' => $item->actual_price,
            'status' => $item->status,
            'notes' => $item->notes,
            'price_source' => $item->price_source,
            'supermarket_chain_id' => $item->supermarket_chain_id,
            'supermarket_branch_id' => $item->supermarket_branch_id,
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
