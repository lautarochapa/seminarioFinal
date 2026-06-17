<?php

namespace App\Repositories\MealPlans;

use App\MealPlan;
use App\MealPlanItem;
use App\MealType;
use App\Recipe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MealPlanRepository
{
    public function paginate(int $groupId, array $filters): LengthAwarePaginator
    {
        $query = MealPlan::with(['items.mealType', 'items.recipe'])
            ->where('family_group_id', $groupId)
            ->whereNull('deleted_at');

        if (!empty($filters['date_from'])) {
            $query->where('end_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('start_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['meal_type_id'])) {
            $mtId = (int) $filters['meal_type_id'];
            $query->whereHas('items', function ($q) use ($mtId) {
                $q->where('meal_type_id', $mtId)->whereNull('deleted_at');
            });
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $page    = max((int) ($filters['page'] ?? 1), 1);

        return $query->orderByDesc('start_date')->paginate($perPage, ['*'], 'page', $page);
    }

    public function findForGroup(int $planId, int $groupId): ?MealPlan
    {
        return MealPlan::with(['items.mealType', 'items.recipe'])
            ->where('id', $planId)
            ->where('family_group_id', $groupId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function create(array $planData, array $items): MealPlan
    {
        $plan = MealPlan::create($planData);

        foreach ($items as $item) {
            MealPlanItem::create(array_merge($item, ['meal_plan_id' => $plan->id]));
        }

        return $plan->load(['items.mealType', 'items.recipe']);
    }

    public function update(MealPlan $plan, array $planData, ?array $items): MealPlan
    {
        $plan->fill($planData);
        $plan->save();

        if ($items !== null) {
            MealPlanItem::where('meal_plan_id', $plan->id)->delete();
            foreach ($items as $item) {
                MealPlanItem::create(array_merge($item, ['meal_plan_id' => $plan->id]));
            }
        }

        return $plan->load(['items.mealType', 'items.recipe']);
    }

    public function delete(MealPlan $plan): void
    {
        $plan->delete();
    }

    public function findActiveMealType(int $id): ?MealType
    {
        return MealType::where('id', $id)->where('status', 'active')->first();
    }

    public function findActiveRecipe(int $id): ?Recipe
    {
        return Recipe::where('id', $id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
    }
}
