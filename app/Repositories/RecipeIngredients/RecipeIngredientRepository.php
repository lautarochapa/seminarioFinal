<?php

namespace App\Repositories\RecipeIngredients;

use App\Ingredient;
use App\RecipeIngredient;
use App\UnitMeasure;

class RecipeIngredientRepository
{
    public function findOrFail($recipeId, $id)
    {
        return RecipeIngredient::with(['ingredient', 'unit', 'specificProduct'])
            ->where('recipe_id', $recipeId)
            ->findOrFail($id);
    }

    public function existsInRecipe($recipeId, $ingredientId, $ignoreId = null)
    {
        $query = RecipeIngredient::where('recipe_id', $recipeId)
            ->where('ingredient_id', $ingredientId);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function activeIngredient($id)
    {
        return Ingredient::where('id', $id)->where('status', 'active')->whereNull('deleted_at')->first();
    }

    public function activeUnit($id)
    {
        return UnitMeasure::where('id', $id)->where('status', 'active')->first();
    }
}
