<?php

namespace App\Repositories\MealPlanItemStatus;

use App\MealConsumptionLog;
use App\MealPlanItem;
use App\RecipeCookLog;

class MealPlanItemStatusRepository
{
    public function findForPlan(int $itemId, int $planId): ?MealPlanItem
    {
        return MealPlanItem::with(['mealType', 'recipe', 'portions'])
            ->where('id', $itemId)
            ->where('meal_plan_id', $planId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function update(MealPlanItem $item, array $data): MealPlanItem
    {
        $item->fill($data);
        $item->save();

        return $item->load(['mealType', 'recipe']);
    }

    public function createCookLog(array $data): RecipeCookLog
    {
        return RecipeCookLog::create($data);
    }

    public function createConsumptionLog(array $data): MealConsumptionLog
    {
        return MealConsumptionLog::updateOrCreate(
            [
                'meal_plan_item_id' => $data['meal_plan_item_id'],
                'user_id' => $data['user_id'],
            ],
            $data
        );
    }
}
