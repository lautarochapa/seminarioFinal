<?php

namespace App\Repositories\ShoppingLists;

use App\MealPlan;
use App\ShoppingList;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShoppingListRepository
{
    public function paginateForGroup(int $groupId, array $filters): LengthAwarePaginator
    {
        $query = ShoppingList::where('family_group_id', $groupId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findInGroup(int $groupId, int $listId): ?ShoppingList
    {
        return ShoppingList::with(['items.ingredient', 'items.product', 'items.unit'])
            ->where('family_group_id', $groupId)
            ->where('id', $listId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function create(array $data): ShoppingList
    {
        return ShoppingList::create($data);
    }

    public function update(ShoppingList $list, array $data): ShoppingList
    {
        $list->fill($data);
        $list->save();

        return $list->fresh(['items.ingredient', 'items.product', 'items.unit']);
    }

    public function delete(ShoppingList $list): ShoppingList
    {
        $list->delete();

        return ShoppingList::withTrashed()->find($list->id);
    }

    public function mealPlanInGroup(int $groupId, int $planId): bool
    {
        return MealPlan::where('id', $planId)
            ->where('family_group_id', $groupId)
            ->whereNull('deleted_at')
            ->exists();
    }
}
