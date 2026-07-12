<?php

namespace App\Services\ShoppingLists;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ShoppingLists\ShoppingListRepository;
use App\ShoppingList;
use App\User;

class ShoppingListService
{
    private $groups;
    private $lists;

    public function __construct(FamilyGroupRepository $groups, ShoppingListRepository $lists)
    {
        $this->groups = $groups;
        $this->lists = $lists;
    }

    public function list(User $user, int $groupId, array $filters)
    {
        $this->assertMember($user, $groupId);

        return $this->lists->paginateForGroup($groupId, $filters);
    }

    public function show(User $user, int $groupId, int $listId): ShoppingList
    {
        $this->assertMember($user, $groupId);

        return $this->findList($groupId, $listId);
    }

    public function create(User $user, int $groupId, array $data, string $ip, string $ua): ShoppingList
    {
        $this->assertMember($user, $groupId);
        $sourceType = $data['source_type'] ?? 'manual';
        $mealPlanId = $data['meal_plan_id'] ?? null;

        if ($mealPlanId !== null && !$this->lists->mealPlanInGroup($groupId, (int) $mealPlanId)) {
            throw new FamilyGroupException('SHOPPING_LIST_MEAL_PLAN_NOT_FOUND', 'El plan de comidas no existe para este grupo.', 422);
        }

        $list = $this->lists->create([
            'family_group_id' => $groupId,
            'meal_plan_id' => $mealPlanId,
            'created_by' => $user->id,
            'source_type' => $sourceType,
            'status' => $data['status'] ?? ShoppingList::STATUS_DRAFT,
            'optimization_mode' => $data['optimization_mode'] ?? null,
        ]);

        $this->audit($user->id, 'shopping_list.created', $list->id, null, $this->payload($list), $ip, $ua);

        return $list->load(['items.ingredient', 'items.product', 'items.unit']);
    }

    public function update(User $user, int $groupId, int $listId, array $data, string $ip, string $ua): ShoppingList
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);

        if (isset($data['meal_plan_id']) && $data['meal_plan_id'] !== null && !$this->lists->mealPlanInGroup($groupId, (int) $data['meal_plan_id'])) {
            throw new FamilyGroupException('SHOPPING_LIST_MEAL_PLAN_NOT_FOUND', 'El plan de comidas no existe para este grupo.', 422);
        }

        if (isset($data['status']) && $data['status'] !== $list->status && !$list->canTransitionTo($data['status'])) {
            throw new FamilyGroupException(
                'SHOPPING_LIST_INVALID_STATUS_TRANSITION',
                'No se puede pasar la lista de "'.$list->statusLabel().'" a ese estado.',
                409
            );
        }

        $allowed = ['meal_plan_id', 'source_type', 'status', 'optimization_mode'];
        $fields = array_intersect_key($data, array_flip($allowed));
        $old = $this->payload($list);
        $updated = $this->lists->update($list, $fields);

        $this->audit($user->id, 'shopping_list.updated', $updated->id, $old, $this->payload($updated), $ip, $ua);

        return $updated;
    }

    public function start(User $user, int $groupId, int $listId, string $ip, string $ua): ShoppingList
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);

        if ($list->status === ShoppingList::STATUS_IN_PROGRESS) {
            // Idempotent: starting an already-started list is a no-op, not an error.
            return $list;
        }

        if (!$list->canTransitionTo(ShoppingList::STATUS_IN_PROGRESS)) {
            throw new FamilyGroupException(
                'SHOPPING_LIST_INVALID_STATUS_TRANSITION',
                'La lista debe estar "Lista para comprar" para comenzar la compra.',
                409
            );
        }

        if ($list->items()->count() === 0) {
            throw new FamilyGroupException('SHOPPING_LIST_EMPTY', 'La lista no tiene articulos para comprar.', 422);
        }

        $old = $this->payload($list);
        $list->status = ShoppingList::STATUS_IN_PROGRESS;
        $list->save();

        $this->audit($user->id, 'shopping_list.started', $list->id, $old, $this->payload($list), $ip, $ua);

        return $list->fresh(['items.ingredient', 'items.product', 'items.unit']);
    }

    public function cancel(User $user, int $groupId, int $listId, string $ip, string $ua): ShoppingList
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);

        if ($list->status === ShoppingList::STATUS_CANCELLED) {
            return $list;
        }

        if (!$list->canTransitionTo(ShoppingList::STATUS_CANCELLED)) {
            throw new FamilyGroupException(
                'SHOPPING_LIST_INVALID_STATUS_TRANSITION',
                'No se puede cancelar la lista desde "'.$list->statusLabel().'".',
                409
            );
        }

        $old = $this->payload($list);
        $list->status = ShoppingList::STATUS_CANCELLED;
        $list->save();

        $this->audit($user->id, 'shopping_list.cancelled', $list->id, $old, $this->payload($list), $ip, $ua);

        return $list->fresh(['items.ingredient', 'items.product', 'items.unit']);
    }

    public function delete(User $user, int $groupId, int $listId, string $ip, string $ua): ShoppingList
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);
        $old = $this->payload($list);
        $deleted = $this->lists->delete($list);

        $this->audit($user->id, 'shopping_list.deleted', $deleted->id, $old, ['deleted' => true], $ip, $ua);

        return $deleted;
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

    private function payload(ShoppingList $list): array
    {
        return [
            'family_group_id' => $list->family_group_id,
            'meal_plan_id' => $list->meal_plan_id,
            'source_type' => $list->source_type,
            'status' => $list->status,
            'optimization_mode' => $list->optimization_mode,
        ];
    }

    private function audit(int $userId, string $action, int $listId, ?array $old, array $new, string $ip, string $ua): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_name' => 'shopping_lists',
            'entity_id' => (string) $listId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }
}
