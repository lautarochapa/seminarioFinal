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
            ->where(function ($q) { $q->whereNull('si.expiration_date')->orWhereDate('si.expiration_date', '>=', now()->toDateString()); })
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
            ->where(function ($q) { $q->whereNull('si.expiration_date')->orWhereDate('si.expiration_date', '>=', now()->toDateString()); })
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

    public function allActiveConversions(): Collection
    {
        return UnitConversion::where('status', 'active')->get()->groupBy('from_unit_id');
    }

    /**
     * Returns [ product_id => [ unit_id => total_qty ] ] for the given products,
     * counting only active, non-deleted, non-expired stock.
     */
    public function stockByProducts(int $familyGroupId, array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $rows = DB::table('stock_items as si')
            ->where('si.family_group_id', $familyGroupId)
            ->whereIn('si.product_id', $productIds)
            ->where('si.status', 'active')
            ->whereNull('si.deleted_at')
            ->where(function ($q) { $q->whereNull('si.expiration_date')->orWhereDate('si.expiration_date', '>=', now()->toDateString()); })
            ->select('si.product_id', 'si.unit_id', DB::raw('SUM(si.quantity) as total_qty'))
            ->groupBy('si.product_id', 'si.unit_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->product_id][(int) $row->unit_id] = (float) $row->total_qty;
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

        if ($conv) {
            return (float) $conv->factor;
        }

        $inverse = UnitConversion::where('from_unit_id', $toUnitId)
            ->where('to_unit_id', $fromUnitId)
            ->where(function ($q) use ($ingredientId) {
                if ($ingredientId) { $q->where('ingredient_id', $ingredientId)->orWhereNull('ingredient_id'); }
                else { $q->whereNull('ingredient_id'); }
            })->where('status', 'active')->orderByRaw('CASE WHEN ingredient_id IS NOT NULL THEN 0 ELSE 1 END')->first();

        return $inverse && (float) $inverse->factor > 0 ? 1 / (float) $inverse->factor : null;
    }
}
