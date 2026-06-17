<?php

namespace App\Repositories\RecipeSubstitutions;

use App\FamilyGroup;
use App\Recipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecipeSubstitutionsRepository
{
    public function findVisible(int $id, int $userId): ?Recipe
    {
        return Recipe::where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($userId) {
                $q->where('is_public', true)->orWhere('owner_user_id', $userId);
            })
            ->find($id);
    }

    public function findFamilyGroup(int $id): ?FamilyGroup
    {
        return FamilyGroup::where('status', 'active')->find($id);
    }

    public function isFamilyMember(int $groupId, int $userId): bool
    {
        return DB::table('family_group_members')
            ->where('family_group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Returns recipe ingredients with their ingredient details.
     * Shape: [{ recipe_ingredient_id, ingredient_id, ingredient_name, quantity, unit_id, unit_symbol, is_optional }]
     */
    public function recipeIngredients(int $recipeId): Collection
    {
        return DB::table('recipe_ingredients as ri')
            ->join('ingredients as i', 'i.id', '=', 'ri.ingredient_id')
            ->join('unit_measures as u', 'u.id', '=', 'ri.unit_id')
            ->where('ri.recipe_id', $recipeId)
            ->whereNull('i.deleted_at')
            ->select(
                'ri.id as recipe_ingredient_id',
                'ri.ingredient_id',
                'i.name as ingredient_name',
                'ri.quantity',
                'ri.unit_id',
                'u.symbol as unit_symbol',
                'ri.is_optional'
            )
            ->get();
    }

    /**
     * Returns active ingredient equivalences for a set of ingredient IDs.
     * Shape: [{ source_ingredient_id, target_ingredient_id, equivalence_type, conversion_factor, reason,
     *           target_name, target_base_unit_id, target_base_unit_symbol }]
     */
    public function equivalencesForIngredients(array $ingredientIds): Collection
    {
        if (empty($ingredientIds)) {
            return collect();
        }

        return DB::table('ingredient_equivalences as ie')
            ->join('ingredients as ti', 'ti.id', '=', 'ie.target_ingredient_id')
            ->join('unit_measures as tu', 'tu.id', '=', 'ti.base_unit_id')
            ->where('ie.status', 'active')
            ->whereIn('ie.source_ingredient_id', $ingredientIds)
            ->whereNull('ti.deleted_at')
            ->where('ti.status', 'active')
            ->select(
                'ie.source_ingredient_id',
                'ie.target_ingredient_id',
                'ie.equivalence_type',
                'ie.conversion_factor',
                'ie.reason',
                'ti.name as target_name',
                'ti.base_unit_id as target_base_unit_id',
                'tu.symbol as target_base_unit_symbol'
            )
            ->get();
    }

    /**
     * Returns recipe-specific substitution hints for a set of ingredient IDs.
     * Shape: [{ source_ingredient_id, target_ingredient_id, reason, status }]
     */
    public function recipeSubstitutions(int $recipeId, array $ingredientIds): Collection
    {
        if (empty($ingredientIds)) {
            return collect();
        }

        return DB::table('recipe_substitutions')
            ->where('recipe_id', $recipeId)
            ->where('status', 'active')
            ->whereIn('source_ingredient_id', $ingredientIds)
            ->select('source_ingredient_id', 'target_ingredient_id', 'reason as recipe_reason')
            ->get();
    }

    /**
     * Returns ingredient IDs available in a family group's stock.
     */
    public function stockedIngredientIds(int $groupId): array
    {
        return DB::table('stock_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.family_group_id', $groupId)
            ->where('si.status', 'active')
            ->whereNull('si.deleted_at')
            ->where('si.quantity', '>', 0)
            ->whereNotNull('p.ingredient_id')
            ->distinct()
            ->pluck('p.ingredient_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }
}
