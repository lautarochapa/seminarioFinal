<?php

namespace App\Repositories\Nutrients;

use App\Exceptions\Nutrients\NutrientException;
use App\Ingredient;
use App\IngredientNutrient;

class IngredientNutrientRepository
{
    public function findIngredientOrFail($ingredientId)
    {
        $ingredient = Ingredient::where('id', $ingredientId)->where('status', 'active')->first();

        if (! $ingredient) {
            throw new NutrientException('NUTRIENT_INGREDIENT_NOT_FOUND', 'El ingrediente solicitado no existe o no está activo.', 404);
        }

        return $ingredient;
    }

    public function listForIngredient($ingredientId)
    {
        return IngredientNutrient::with(['nutrient.unit'])
            ->where('ingredient_id', $ingredientId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
    }

    public function findRelationOrFail($ingredientId, $nutrientId)
    {
        $relation = IngredientNutrient::with(['nutrient.unit'])
            ->where('ingredient_id', $ingredientId)
            ->where('nutrient_id', $nutrientId)
            ->first();

        if (! $relation) {
            throw new NutrientException('NUTRIENT_NOT_FOUND', 'La relación nutricional solicitada no existe.', 404);
        }

        return $relation;
    }

    public function activeRelationExists($ingredientId, $nutrientId, $exceptId = null)
    {
        $query = IngredientNutrient::where('ingredient_id', $ingredientId)
            ->where('nutrient_id', $nutrientId)
            ->where('status', 'active');

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return IngredientNutrient::create($data)->fresh(['nutrient.unit']);
    }

    public function update(IngredientNutrient $relation, array $data)
    {
        $relation->fill($data);
        $relation->save();

        return $relation->fresh(['nutrient.unit']);
    }
}
