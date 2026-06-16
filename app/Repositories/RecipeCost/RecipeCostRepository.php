<?php

namespace App\Repositories\RecipeCost;

use App\FamilyGroup;
use App\Recipe;
use App\RecipeCostSnapshot;
use App\StockItem;
use App\SupermarketProductPrice;
use Illuminate\Support\Collection;

class RecipeCostRepository
{
    public function findActiveOrFail($id): Recipe
    {
        return Recipe::where('status', 'active')->findOrFail($id);
    }

    public function findWithTrashedOrFail($id): Recipe
    {
        return Recipe::withTrashed()->findOrFail($id);
    }

    public function loadIngredientsForCost($recipeId): Recipe
    {
        return Recipe::with([
            'ingredients.ingredient',
            'ingredients.unit',
            'ingredients.specificProduct.packageUnit',
            'ingredients.specificProduct.supermarketProducts.prices',
        ])->withTrashed()->findOrFail($recipeId);
    }

    public function findFamilyGroup($id): ?FamilyGroup
    {
        return FamilyGroup::where('status', 'active')->find($id);
    }

    public function isFamilyMember(int $familyGroupId, int $userId): bool
    {
        return \DB::table('family_group_members')
            ->where('family_group_id', $familyGroupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    public function cheapestPriceForProduct(int $productId): ?SupermarketProductPrice
    {
        return SupermarketProductPrice::whereHas('supermarketProduct', function ($q) use ($productId) {
            $q->where('product_id', $productId)->where('status', 'active');
        })
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>', now());
            })
            ->orderBy('price')
            ->first();
    }

    public function cheapestPriceForIngredient(int $ingredientId): ?array
    {
        $price = SupermarketProductPrice::whereHas('supermarketProduct.product', function ($q) use ($ingredientId) {
            $q->where('ingredient_id', $ingredientId)->where('status', 'active');
        })
            ->whereHas('supermarketProduct', function ($q) {
                $q->where('status', 'active');
            })
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>', now());
            })
            ->with('supermarketProduct.product')
            ->orderBy('price')
            ->first();

        if (!$price) {
            return null;
        }

        return [
            'price'      => (float) $price->price,
            'currency'   => $price->currency,
            'product'    => $price->supermarketProduct->product,
        ];
    }

    public function stockPriceForProduct(int $familyGroupId, int $productId): ?array
    {
        $item = StockItem::where('family_group_id', $familyGroupId)
            ->where('product_id', $productId)
            ->whereNotNull('estimated_purchase_price')
            ->where('estimated_purchase_price', '>', 0)
            ->where(function ($q) {
                $q->where('quantity', '>', 0);
            })
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderByDesc('purchase_date')
            ->first();

        if (!$item) {
            return null;
        }

        return [
            'price_per_unit' => (float) $item->estimated_purchase_price / (float) $item->quantity,
            'unit_id'        => $item->unit_id,
            'currency'       => null,
        ];
    }

    public function stockPriceForIngredient(int $familyGroupId, int $ingredientId): ?array
    {
        $item = StockItem::whereHas('product', function ($q) use ($ingredientId) {
            $q->where('ingredient_id', $ingredientId);
        })
            ->where('family_group_id', $familyGroupId)
            ->whereNotNull('estimated_purchase_price')
            ->where('estimated_purchase_price', '>', 0)
            ->where('quantity', '>', 0)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with('product')
            ->orderByDesc('purchase_date')
            ->first();

        if (!$item) {
            return null;
        }

        return [
            'price_per_unit' => (float) $item->estimated_purchase_price / (float) $item->quantity,
            'unit_id'        => $item->unit_id,
            'currency'       => null,
            'product'        => $item->product,
        ];
    }

    public function findConversion(int $fromUnitId, int $toUnitId, ?int $ingredientId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return 1.0;
        }

        $conv = \App\UnitConversion::where('from_unit_id', $fromUnitId)
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

    public function insertSnapshot(array $data): RecipeCostSnapshot
    {
        return RecipeCostSnapshot::create($data);
    }

    public function latestSnapshot($recipeId, ?int $familyGroupId): ?RecipeCostSnapshot
    {
        return RecipeCostSnapshot::where('recipe_id', $recipeId)
            ->where(function ($q) use ($familyGroupId) {
                if ($familyGroupId) {
                    $q->where('family_group_id', $familyGroupId);
                } else {
                    $q->whereNull('family_group_id');
                }
            })
            ->orderByDesc('calculated_at')
            ->first();
    }
}
