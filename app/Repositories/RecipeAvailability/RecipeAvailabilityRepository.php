<?php

namespace App\Repositories\RecipeAvailability;

use App\FamilyGroup;
use App\Recipe;
use App\UnitConversion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecipeAvailabilityRepository
{
    public function findActiveOrFail($id): Recipe
    {
        return Recipe::where('status', 'active')->findOrFail($id);
    }

    public function loadRecipeIngredients($recipeId): Recipe
    {
        return Recipe::with([
            'ingredients.ingredient',
            'ingredients.unit',
        ])->where('status', 'active')->findOrFail($recipeId);
    }

    public function findFamilyGroup($id): ?FamilyGroup
    {
        return FamilyGroup::where('status', 'active')->find($id);
    }

    public function isFamilyMember(int $familyGroupId, int $userId): bool
    {
        return DB::table('family_group_members')
            ->where('family_group_id', $familyGroupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Returns a map of ingredient_id -> total quantity per unit_id.
     * [ ingredient_id => [ unit_id => total_qty ] ]
     */
    public function stockByIngredient(int $familyGroupId): array
    {
        $rows = DB::table('stock_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.family_group_id', $familyGroupId)
            ->where('si.status', 'active')
            ->whereNull('si.deleted_at')
            ->whereNotNull('p.ingredient_id')
            ->select('p.ingredient_id', 'si.unit_id', DB::raw('SUM(si.quantity) as total_qty'))
            ->groupBy('p.ingredient_id', 'si.unit_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $ingId  = (int) $row->ingredient_id;
            $unitId = (int) $row->unit_id;
            if (!isset($map[$ingId])) {
                $map[$ingId] = [];
            }
            $map[$ingId][$unitId] = (float) $row->total_qty;
        }

        return $map;
    }

    /**
     * Returns a map of ingredient_id -> total quantity per unit_id, filtered to a specific product.
     */
    public function stockByProduct(int $familyGroupId, int $productId): array
    {
        $rows = DB::table('stock_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.family_group_id', $familyGroupId)
            ->where('si.product_id', $productId)
            ->where('si.status', 'active')
            ->whereNull('si.deleted_at')
            ->select('p.ingredient_id', 'si.unit_id', DB::raw('SUM(si.quantity) as total_qty'))
            ->groupBy('p.ingredient_id', 'si.unit_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $unitId = (int) $row->unit_id;
            $map[$unitId] = (float) $row->total_qty;
        }

        return $map;
    }

    public function findConversionFactor(int $fromUnitId, int $toUnitId, ?int $ingredientId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return 1.0;
        }

        $conv = UnitConversion::where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->where(function ($q) use ($ingredientId) {
                if ($ingredientId) {
                    $q->where('ingredient_id', $ingredientId)->orWhereNull('ingredient_id');
                } else {
                    $q->whereNull('ingredient_id');
                }
            })
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN ingredient_id IS NOT NULL THEN 0 ELSE 1 END')
            ->first();

        return $conv ? (float) $conv->factor : null;
    }
}
