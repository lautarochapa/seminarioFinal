<?php

namespace App\Repositories\MealPlanPortions;

use App\FamilyGroupMember;
use App\MealPlanItem;
use App\MealPlanItemPortion;
use Illuminate\Support\Collection;

class MealPlanPortionRepository
{
    public function itemExistsForContext(int $itemId, int $planId, int $groupId): bool
    {
        return MealPlanItem::join('meal_plans', 'meal_plan_items.meal_plan_id', '=', 'meal_plans.id')
            ->where('meal_plan_items.id', $itemId)
            ->where('meal_plan_items.meal_plan_id', $planId)
            ->where('meal_plans.family_group_id', $groupId)
            ->whereNull('meal_plan_items.deleted_at')
            ->whereNull('meal_plans.deleted_at')
            ->exists();
    }

    public function memberBelongsToGroup(int $userId, int $groupId): bool
    {
        return FamilyGroupMember::where('user_id', $userId)
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->exists();
    }

    public function listForItem(int $itemId): Collection
    {
        return MealPlanItemPortion::with('user')
            ->where('meal_plan_item_id', $itemId)
            ->orderBy('user_id')
            ->get();
    }

    public function findForItem(int $portionId, int $itemId): ?MealPlanItemPortion
    {
        return MealPlanItemPortion::with('user')
            ->where('id', $portionId)
            ->where('meal_plan_item_id', $itemId)
            ->first();
    }

    public function duplicateExists(int $itemId, int $userId): bool
    {
        return MealPlanItemPortion::where('meal_plan_item_id', $itemId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function create(array $data): MealPlanItemPortion
    {
        $portion = MealPlanItemPortion::create($data);
        return $portion->load('user');
    }

    public function update(MealPlanItemPortion $portion, array $data): MealPlanItemPortion
    {
        $portion->fill($data);
        $portion->save();
        return $portion->load('user');
    }
}
