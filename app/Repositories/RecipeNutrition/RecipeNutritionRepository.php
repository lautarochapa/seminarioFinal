<?php

namespace App\Repositories\RecipeNutrition;

use App\Recipe;
use App\RecipeNutrition;
use App\UnitMeasure;
use App\UnitConversion;

class RecipeNutritionRepository
{
    public function findActiveOrFail($id): Recipe
    {
        return Recipe::where('status', 'active')->findOrFail($id);
    }

    public function findWithTrashedOrFail($id): Recipe
    {
        return Recipe::withTrashed()->findOrFail($id);
    }

    public function findNutrition($recipeId): ?RecipeNutrition
    {
        return RecipeNutrition::where('recipe_id', $recipeId)->first();
    }

    public function loadForCalculation($recipeId): Recipe
    {
        return Recipe::with([
            'ingredients.ingredient.nutrients',
            'ingredients.ingredient.baseUnit',
            'ingredients.unit',
        ])->withTrashed()->findOrFail($recipeId);
    }

    public function upsertNutrition($recipeId, array $data): RecipeNutrition
    {
        return RecipeNutrition::updateOrCreate(
            ['recipe_id' => $recipeId],
            $data
        );
    }

    public function gramsUnit(): ?UnitMeasure
    {
        return UnitMeasure::where('code', 'g')->where('status', 'active')->first();
    }

    public function findConversionToGrams(int $fromUnitId, int $gramsUnitId, int $ingredientId): ?UnitConversion
    {
        return UnitConversion::where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $gramsUnitId)
            ->where(function ($q) use ($ingredientId) {
                $q->where('ingredient_id', $ingredientId)->orWhereNull('ingredient_id');
            })
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN ingredient_id IS NOT NULL THEN 0 ELSE 1 END')
            ->first();
    }
}
