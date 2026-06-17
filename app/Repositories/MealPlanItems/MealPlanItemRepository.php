<?php

namespace App\Repositories\MealPlanItems;

use App\MealPlan;
use App\MealPlanItem;
use Illuminate\Support\Collection;

class MealPlanItemRepository
{
    public function planExistsForGroup(int $planId, int $groupId): bool
    {
        return MealPlan::where('id', $planId)
            ->where('family_group_id', $groupId)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function listForPlan(int $planId): Collection
    {
        return MealPlanItem::with(['mealType', 'recipe'])
            ->where('meal_plan_id', $planId)
            ->whereNull('deleted_at')
            ->orderBy('date')
            ->orderBy('meal_type_id')
            ->get();
    }

    public function findForPlan(int $itemId, int $planId): ?MealPlanItem
    {
        return MealPlanItem::with(['mealType', 'recipe'])
            ->where('id', $itemId)
            ->where('meal_plan_id', $planId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function create(array $data): MealPlanItem
    {
        $item = MealPlanItem::create($data);
        return $item->load(['mealType', 'recipe']);
    }

    public function update(MealPlanItem $item, array $data): MealPlanItem
    {
        $item->fill($data);
        $item->save();
        return $item->load(['mealType', 'recipe']);
    }

    public function delete(MealPlanItem $item): void
    {
        $item->delete();
    }

    public function duplicateExists(int $planId, string $date, int $mealTypeId, ?int $excludeId = null): bool
    {
        $query = MealPlanItem::where('meal_plan_id', $planId)
            ->where('date', $date)
            ->where('meal_type_id', $mealTypeId)
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
