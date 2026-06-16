<?php

namespace App\Repositories\RecipeSteps;

use App\RecipeStep;

class RecipeStepRepository
{
    public function findOrFail($recipeId, $stepId)
    {
        return RecipeStep::where('recipe_id', $recipeId)->findOrFail($stepId);
    }

    public function stepNumberExists($recipeId, $stepNumber, $ignoreId = null)
    {
        $query = RecipeStep::where('recipe_id', $recipeId)
            ->where('step_number', $stepNumber);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function nextStepNumber($recipeId)
    {
        $max = RecipeStep::where('recipe_id', $recipeId)->max('step_number');

        return $max ? $max + 1 : 1;
    }
}
