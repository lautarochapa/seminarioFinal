<?php

namespace App\Repositories\RecipeSuggestions;

use App\Budget;
use App\FamilyGroup;
use App\Recipe;
use App\RecipeCostSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecipeSuggestionsRepository
{
    public function findFamilyGroup($id): ?FamilyGroup
    {
        return FamilyGroup::where('status', 'active')->find($id);
    }

    public function isMember(int $groupId, int $userId): bool
    {
        return DB::table('family_group_members')
            ->where('family_group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    /** Returns all active visible recipes with their ingredients loaded for availability. */
    public function candidateRecipes(int $userId, bool $canManage): Collection
    {
        return Recipe::with([
            'ingredients' => function ($q) {
                $q->select(['id', 'recipe_id', 'ingredient_id', 'unit_id', 'quantity', 'is_optional', 'specific_product_id', 'sort_order']);
            },
            'ingredients.ingredient:id,name',
            'ingredients.unit:id,name,symbol',
        ])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->when(!$canManage, function ($q) use ($userId) {
                $q->where(function ($inner) use ($userId) {
                    $inner->where('is_public', true)->orWhere('owner_user_id', $userId);
                });
            })
            ->select(['id', 'name', 'difficulty', 'prep_time_minutes', 'cook_time_minutes', 'servings', 'category_id', 'source_type', 'is_official', 'is_public', 'owner_user_id', 'status', 'created_at'])
            ->get();
    }

    /** ingredient_ids of stock expiring within $days. */
    public function expiringIngredientIds(int $groupId, int $days = 7): array
    {
        return DB::table('stock_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.family_group_id', $groupId)
            ->where('si.status', 'active')
            ->whereNull('si.deleted_at')
            ->whereNotNull('si.expiration_date')
            ->whereNotNull('p.ingredient_id')
            ->where('si.expiration_date', '<=', now()->addDays($days)->toDateString())
            ->where('si.expiration_date', '>=', now()->toDateString())
            ->distinct()
            ->pluck('p.ingredient_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /** Current month budget for a family group. */
    public function currentBudget(int $groupId): ?object
    {
        return DB::table('budgets')
            ->where('family_group_id', $groupId)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
    }

    /** Recipe IDs with a snapshot total cost <= maxCost. */
    public function recipeIdsByMaxCost(int $groupId, float $maxCost): array
    {
        return RecipeCostSnapshot::where('estimated_total_cost', '<=', $maxCost)
            ->where(function ($q) use ($groupId) {
                $q->where('family_group_id', $groupId)->orWhereNull('family_group_id');
            })
            ->orderByRaw('CASE WHEN family_group_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('calculated_at')
            ->get()
            ->unique('recipe_id')
            ->pluck('recipe_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    public function recipeCountForFamilyGroup(int $groupId): int
    {
        return RecipeCostSnapshot::where('family_group_id', $groupId)->count();
    }
}
