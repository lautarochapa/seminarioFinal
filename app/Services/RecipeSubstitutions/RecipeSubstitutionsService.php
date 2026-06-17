<?php

namespace App\Services\RecipeSubstitutions;

use App\Exceptions\RecipeSubstitutions\RecipeSubstitutionsException;
use App\Repositories\RecipeSubstitutions\RecipeSubstitutionsRepository;
use App\User;

class RecipeSubstitutionsService
{
    private RecipeSubstitutionsRepository $repo;

    public function __construct(RecipeSubstitutionsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function substitutions(User $user, int $recipeId, array $filters): array
    {
        $recipe = $this->repo->findVisible($recipeId, $user->id);
        if (!$recipe) {
            throw RecipeSubstitutionsException::recipeNotFound();
        }

        $familyGroupId        = isset($filters['family_group_id']) ? (int) $filters['family_group_id'] : null;
        $excludeIngredientIds = isset($filters['exclude_ingredient_ids']) ? array_map('intval', (array) $filters['exclude_ingredient_ids']) : [];

        if ($familyGroupId !== null) {
            $group = $this->repo->findFamilyGroup($familyGroupId);
            if (!$group) {
                throw RecipeSubstitutionsException::familyGroupNotFound();
            }
            if (!$this->repo->isFamilyMember($familyGroupId, $user->id)) {
                throw RecipeSubstitutionsException::familyGroupAccessDenied();
            }
        }

        $recipeIngredients = $this->repo->recipeIngredients($recipeId);
        $ingredientIds     = $recipeIngredients->pluck('ingredient_id')->map(fn ($id) => (int) $id)->toArray();

        $equivalences         = $this->repo->equivalencesForIngredients($ingredientIds);
        $recipeSubstitutions  = $this->repo->recipeSubstitutions($recipeId, $ingredientIds);
        $stockedIds           = $familyGroupId !== null ? $this->repo->stockedIngredientIds($familyGroupId) : null;

        $recipeHintMap = [];
        foreach ($recipeSubstitutions as $rs) {
            $recipeHintMap[(int) $rs->source_ingredient_id][(int) $rs->target_ingredient_id] = $rs->recipe_reason ?? null;
        }

        $equivBySource = [];
        foreach ($equivalences as $eq) {
            $equivBySource[(int) $eq->source_ingredient_id][] = $eq;
        }

        $result = [];

        foreach ($recipeIngredients as $ri) {
            $ingId   = (int) $ri->ingredient_id;
            $equivs  = $equivBySource[$ingId] ?? [];
            $options = [];

            foreach ($equivs as $eq) {
                $targetId = (int) $eq->target_ingredient_id;

                if (in_array($targetId, $excludeIngredientIds, true)) {
                    continue;
                }

                $inStock    = $stockedIds !== null ? in_array($targetId, $stockedIds, true) : null;
                $recipeNote = $recipeHintMap[$ingId][$targetId] ?? null;

                $options[] = [
                    'ingredient_id'          => $targetId,
                    'ingredient_name'        => $eq->target_name,
                    'equivalence_type'       => $eq->equivalence_type,
                    'conversion_factor'      => (float) $eq->conversion_factor,
                    'converted_quantity'     => round((float) $ri->quantity * (float) $eq->conversion_factor, 4),
                    'converted_unit_symbol'  => $eq->target_base_unit_symbol,
                    'reason'                 => $recipeNote ?? $eq->reason,
                    'in_stock'               => $inStock,
                    'recipe_specific'        => isset($recipeHintMap[$ingId][$targetId]),
                ];
            }

            usort($options, function ($a, $b) {
                if ($a['recipe_specific'] !== $b['recipe_specific']) {
                    return $a['recipe_specific'] ? -1 : 1;
                }
                if ($a['in_stock'] !== $b['in_stock'] && $a['in_stock'] !== null) {
                    return $a['in_stock'] ? -1 : 1;
                }
                return 0;
            });

            $result[] = [
                'recipe_ingredient_id' => (int) $ri->recipe_ingredient_id,
                'ingredient_id'        => $ingId,
                'ingredient_name'      => $ri->ingredient_name,
                'quantity'             => (float) $ri->quantity,
                'unit_symbol'          => $ri->unit_symbol,
                'is_optional'          => (bool) $ri->is_optional,
                'has_alternatives'     => !empty($options),
                'alternatives'         => $options,
            ];
        }

        return [
            'recipe_id'    => $recipeId,
            'ingredients'  => $result,
            'filters_applied' => [
                'family_group_id'        => $familyGroupId,
                'exclude_ingredient_ids' => $excludeIngredientIds,
            ],
            'notes' => [
                'objective_filtering'  => 'not_available',
                'restriction_filtering'=> 'not_available',
                'note'                 => 'Ingredient-level dietary restriction and objective compatibility is not supported in the current schema.',
            ],
        ];
    }
}
