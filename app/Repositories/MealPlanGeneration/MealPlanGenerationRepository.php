<?php

namespace App\Repositories\MealPlanGeneration;

use App\FamilyGroupMember;
use App\MealPlan;
use App\MealPlanItem;
use App\MealPlanSuggestion;
use App\MealType;
use App\Recipe;
use Illuminate\Support\Collection;

class MealPlanGenerationRepository
{
    public function activeMealTypes(): Collection
    {
        return MealType::where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function groupPreference(int $groupId): ?\App\MealPlanPreference
    {
        return \App\MealPlanPreference::where('family_group_id', $groupId)
            ->whereNull('user_id')
            ->first();
    }

    public function candidateRecipesForGroup(int $groupId, ?array $allowedRecipeIds): array
    {
        $memberIds = FamilyGroupMember::where('family_group_id', $groupId)
            ->where('status', 'active')
            ->pluck('user_id')
            ->toArray();

        $query = Recipe::where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($memberIds) {
                $q->where('is_public', true)
                  ->orWhereIn('owner_user_id', $memberIds);
            });

        if ($allowedRecipeIds !== null && count($allowedRecipeIds) > 0) {
            $query->whereIn('id', $allowedRecipeIds);
        }

        return $query->select(['id'])
            ->get()
            ->map(fn($r) => ['id' => $r->id, 'score' => 0.0])
            ->toArray();
    }

    public function lockPlanForUpdate(int $planId, int $groupId): ?MealPlan
    {
        return MealPlan::where('id', $planId)
            ->where('family_group_id', $groupId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();
    }

    public function approvePlan(MealPlan $plan): MealPlan
    {
        $plan->status      = 'approved';
        $plan->approved_at = now();
        $plan->save();
        return $plan->load(['items.mealType', 'items.recipe']);
    }

    public function replaceItems(MealPlan $plan, array $items): MealPlan
    {
        MealPlanItem::where('meal_plan_id', $plan->id)->delete();
        foreach ($items as $item) {
            MealPlanItem::create(array_merge($item, ['meal_plan_id' => $plan->id]));
        }
        return $plan->load(['items.mealType', 'items.recipe']);
    }

    public function saveSuggestions(int $groupId, int $planId, array $scoredRecipes): void
    {
        foreach (array_slice($scoredRecipes, 0, 10) as $r) {
            MealPlanSuggestion::create([
                'family_group_id'   => $groupId,
                'meal_plan_id'      => $planId,
                'recipe_id'         => $r['id'],
                'suggestion_reason' => 'auto_generated',
                'score'             => $r['score'],
                'status'            => 'pending',
            ]);
        }
    }
}
