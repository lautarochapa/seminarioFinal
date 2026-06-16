<?php

namespace App\Services\RecipeAvailability;

use App\Exceptions\RecipeAvailability\RecipeAvailabilityException;
use App\Repositories\RecipeAvailability\RecipeAvailabilityRepository;
use App\User;

class RecipeAvailabilityService
{
    private RecipeAvailabilityRepository $repo;

    const STATUS_POSSIBLE        = 'possible';
    const STATUS_ALMOST_POSSIBLE = 'almost_possible';
    const STATUS_NOT_POSSIBLE    = 'not_possible';

    public function __construct(RecipeAvailabilityRepository $repo)
    {
        $this->repo = $repo;
    }

    public function availability(User $user, $recipeId, int $familyGroupId): array
    {
        $recipe = $this->resolveRecipe($user, $recipeId, $familyGroupId);
        return $this->compute($recipe, $familyGroupId, false);
    }

    public function missingIngredients(User $user, $recipeId, int $familyGroupId): array
    {
        $recipe = $this->resolveRecipe($user, $recipeId, $familyGroupId);
        $result = $this->compute($recipe, $familyGroupId, false);

        $missing = array_values(array_filter($result['ingredients'], function ($ing) {
            return $ing['status'] !== 'available';
        }));

        usort($missing, function ($a, $b) {
            return $b['missing_quantity'] <=> $a['missing_quantity'];
        });

        return [
            'recipe_id'  => $result['recipe_id'],
            'status'     => $result['status'],
            'missing'    => $missing,
        ];
    }

    private function resolveRecipe(User $user, $recipeId, int $familyGroupId)
    {
        try {
            $recipe = $this->repo->loadRecipeIngredients($recipeId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw RecipeAvailabilityException::recipeNotFound();
        }

        if (!$recipe->is_public
            && $recipe->owner_user_id !== $user->id
            && !$user->hasPermission('recipes.manage')
        ) {
            throw RecipeAvailabilityException::recipeNotVisible();
        }

        $group = $this->repo->findFamilyGroup($familyGroupId);
        if (!$group) {
            throw RecipeAvailabilityException::familyGroupNotFound();
        }

        if (!$this->repo->isFamilyMember($familyGroupId, $user->id)) {
            throw RecipeAvailabilityException::familyGroupAccessDenied();
        }

        return $recipe;
    }

    private function compute($recipe, int $familyGroupId, bool $includeOptional): array
    {
        $ingredients     = $recipe->ingredients;
        $requiredServings = ($recipe->servings !== null && $recipe->servings > 0) ? (int) $recipe->servings : 1;
        $stockMap        = $this->repo->stockByIngredient($familyGroupId);

        $breakdown   = [];
        $maxServings = PHP_INT_MAX;
        $totalReq    = 0;
        $totalAvail  = 0;

        foreach ($ingredients as $ri) {
            if ($ri->is_optional && !$includeOptional) {
                continue;
            }

            $ingredientId = (int) $ri->ingredient_id;
            $requiredQty  = (float) $ri->quantity;
            $recipeUnitId = (int) $ri->unit_id;
            $ingName      = $ri->ingredient ? $ri->ingredient->name : null;

            // Gather stock for this ingredient, converting each unit to the recipe unit
            if ($ri->specific_product_id) {
                $productStock = $this->repo->stockByProduct($familyGroupId, (int) $ri->specific_product_id);
                $availableQty = $this->sumConvertedStock($productStock, $recipeUnitId, $ingredientId);
            } else {
                $ingStock     = $stockMap[$ingredientId] ?? [];
                $availableQty = $this->sumConvertedStock($ingStock, $recipeUnitId, $ingredientId);
            }

            $missingQty  = max(0.0, $requiredQty - $availableQty);
            $covered     = $availableQty >= $requiredQty;
            $itemStatus  = $covered ? 'available' : ($availableQty > 0 ? 'insufficient' : 'missing');

            $perServing      = $requiredQty / $requiredServings;
            $maxFromThis     = $perServing > 0 ? (int) floor($availableQty / $perServing) : ($covered ? PHP_INT_MAX : 0);
            $maxServings     = min($maxServings, $maxFromThis);

            $totalReq   += $requiredQty;
            $totalAvail += min($availableQty, $requiredQty);

            $breakdown[] = [
                'ingredient_id'      => $ingredientId,
                'ingredient_name'    => $ingName,
                'required_quantity'  => $requiredQty,
                'available_quantity' => round($availableQty, 4),
                'missing_quantity'   => round($missingQty, 4),
                'unit_id'            => $recipeUnitId,
                'status'             => $itemStatus,
                'is_optional'        => (bool) $ri->is_optional,
                'suggested_purchase' => $missingQty > 0 ? round($missingQty, 4) : null,
            ];
        }

        if ($maxServings === PHP_INT_MAX) {
            $maxServings = count($breakdown) > 0 ? $requiredServings : 0;
        }

        $overallStatus = $this->overallStatus($maxServings, $requiredServings);
        $coveragePct   = $totalReq > 0 ? round(min(100.0, ($totalAvail / $totalReq) * 100), 1) : 100.0;

        $suggestedServings = null;
        if ($overallStatus === self::STATUS_ALMOST_POSSIBLE && $maxServings > 0) {
            $suggestedServings = $maxServings;
        }

        return [
            'recipe_id'              => $recipe->id,
            'status'                 => $overallStatus,
            'required_servings'      => $requiredServings,
            'max_possible_servings'  => $maxServings,
            'suggested_servings'     => $suggestedServings,
            'coverage_percentage'    => $coveragePct,
            'ingredients'            => $breakdown,
        ];
    }

    private function sumConvertedStock(array $unitQtyMap, int $targetUnitId, int $ingredientId): float
    {
        $total = 0.0;
        foreach ($unitQtyMap as $unitId => $qty) {
            $factor = $this->repo->findConversionFactor($unitId, $targetUnitId, $ingredientId);
            if ($factor !== null) {
                $total += $qty * $factor;
            }
        }
        return $total;
    }

    private function overallStatus(int $maxServings, int $requiredServings): string
    {
        if ($maxServings >= $requiredServings) {
            return self::STATUS_POSSIBLE;
        }
        if ($maxServings > 0) {
            return self::STATUS_ALMOST_POSSIBLE;
        }
        return self::STATUS_NOT_POSSIBLE;
    }
}
