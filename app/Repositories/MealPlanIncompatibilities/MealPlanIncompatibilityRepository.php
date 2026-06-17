<?php

namespace App\Repositories\MealPlanIncompatibilities;

use App\MealPlan;
use App\MealPlanIncompatibility;
use App\MealPlanItem;
use Illuminate\Support\Collection;

class MealPlanIncompatibilityRepository
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
        return MealPlanIncompatibility::with(['mealPlanItem', 'user'])
            ->where('meal_plan_id', $planId)
            ->orderBy('id')
            ->get();
    }

    public function clearForPlan(int $planId): void
    {
        MealPlanIncompatibility::where('meal_plan_id', $planId)->delete();
    }

    public function createIncompatibility(array $data): MealPlanIncompatibility
    {
        return MealPlanIncompatibility::create($data);
    }

    public function planItemsWithNutrition(int $planId): Collection
    {
        return MealPlanItem::with(['recipe', 'recipe.nutrition'])
            ->where('meal_plan_id', $planId)
            ->whereNull('deleted_at')
            ->whereNotNull('recipe_id')
            ->get();
    }
}
