<?php

namespace App\Services\RecipeAvailability;

use App\Exceptions\RecipeAvailability\RecipeAvailabilityException;
use App\Repositories\RecipeAvailability\RecipeAvailabilityRepository;
use App\User;
use Illuminate\Support\Collection;

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

    public function availability(User $user, $recipeId, int $familyGroupId, ?int $servings = null): array
    {
        $recipe = $this->resolveRecipe($user, $recipeId, $familyGroupId);
        list($stockByIngredient, $stockByProduct, $conversions) = $this->loadStockContext($familyGroupId, collect([$recipe]));

        return $this->compute($recipe, $stockByIngredient, $stockByProduct, $conversions, false, $servings);
    }

    public function missingIngredients(User $user, $recipeId, int $familyGroupId): array
    {
        $recipe = $this->resolveRecipe($user, $recipeId, $familyGroupId);
        list($stockByIngredient, $stockByProduct, $conversions) = $this->loadStockContext($familyGroupId, collect([$recipe]));
        $result = $this->compute($recipe, $stockByIngredient, $stockByProduct, $conversions, false);

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

    /**
     * Availability for many already-loaded, already-visible recipes against a
     * single family group, sharing one stock/conversion load. Same per-recipe
     * shape as availability(). Returns [ recipe_id => result ].
     */
    public function availabilityBatch(Collection $recipes, int $familyGroupId, ?int $servings = null): array
    {
        list($stockByIngredient, $stockByProduct, $conversions) = $this->loadStockContext($familyGroupId, $recipes);

        $out = [];
        foreach ($recipes as $recipe) {
            $out[$recipe->id] = $this->compute($recipe, $stockByIngredient, $stockByProduct, $conversions, false, $servings);
        }

        return $out;
    }

    private function loadStockContext(int $familyGroupId, Collection $recipes): array
    {
        $productIds = $recipes->flatMap(function ($recipe) {
            return collect($recipe->ingredients ?? [])
                ->pluck('specific_product_id')
                ->filter()
                ->map(fn ($id) => (int) $id);
        })->unique()->values()->all();

        return [
            $this->repo->stockByIngredient($familyGroupId),
            $this->repo->stockByProducts($familyGroupId, $productIds),
            $this->repo->allActiveConversions(),
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

    private function compute($recipe, array $stockByIngredient, array $stockByProduct, Collection $conversions, bool $includeOptional, ?int $requestedServings = null): array
    {
        $ingredients     = $recipe->ingredients ?? collect();
        $baseServings = ($recipe->servings !== null && $recipe->servings > 0) ? (int) $recipe->servings : 1;
        $requiredServings = $requestedServings ?: $baseServings;
        $servingFactor = $requiredServings / $baseServings;

        $breakdown   = [];
        $maxServings = PHP_INT_MAX;
        $totalReq    = 0;
        $totalAvail  = 0;

        foreach ($ingredients as $ri) {
            if ($ri->is_optional && !$includeOptional) {
                continue;
            }

            $ingredientId = (int) $ri->ingredient_id;
            $requiredQty  = (float) $ri->quantity * $servingFactor;
            $recipeUnitId = (int) $ri->unit_id;
            $ingName      = $ri->ingredient ? $ri->ingredient->name : null;

            if ($ri->specific_product_id) {
                $sourceStock = $stockByProduct[(int) $ri->specific_product_id] ?? [];
            } else {
                $sourceStock = $stockByIngredient[$ingredientId] ?? [];
            }

            $availableQty    = $this->sumConvertedStock($sourceStock, $recipeUnitId, $ingredientId, $conversions);
            $unitCompatible  = empty($sourceStock) || $this->hasCompatibleUnit($sourceStock, $recipeUnitId, $ingredientId, $conversions);

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
                'unit_name'          => $ri->unit ? $ri->unit->name : null,
                'unit_symbol'        => $ri->unit ? $ri->unit->symbol : null,
                'unit_compatible'    => $unitCompatible,
                'specific_product_id' => $ri->specific_product_id ? (int) $ri->specific_product_id : null,
                'is_available'       => $covered,
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
            'base_servings'          => $baseServings,
            'max_possible_servings'  => $maxServings,
            'suggested_servings'     => $suggestedServings,
            'coverage_percentage'    => $coveragePct,
            'ingredients'            => $breakdown,
            'required_ingredients_count' => count($breakdown),
            'available_ingredients_count' => count(array_filter($breakdown, function ($item) { return $item['status'] === 'available'; })),
            'missing_ingredients_count' => count(array_filter($breakdown, function ($item) { return $item['status'] !== 'available'; })),
            'can_cook' => $overallStatus === self::STATUS_POSSIBLE,
            'warnings' => count(array_filter($breakdown, function ($item) { return ! $item['unit_compatible']; })) > 0
                ? ['Hay unidades de stock que no se pueden convertir para esta receta.'] : [],
        ];
    }

    private function sumConvertedStock(array $unitQtyMap, int $targetUnitId, int $ingredientId, Collection $conversions): float
    {
        $total = 0.0;
        foreach ($unitQtyMap as $unitId => $qty) {
            $factor = $this->resolveFactor($conversions, (int) $unitId, $targetUnitId, $ingredientId);
            if ($factor !== null) {
                $total += $qty * $factor;
            }
        }
        return $total;
    }

    private function hasCompatibleUnit(array $unitQtyMap, int $targetUnitId, int $ingredientId, Collection $conversions): bool
    {
        foreach ($unitQtyMap as $unitId => $qty) {
            if ((float) $qty > 0 && $this->resolveFactor($conversions, (int) $unitId, $targetUnitId, $ingredientId) !== null) {
                return true;
            }
        }
        return false;
    }

    private function resolveFactor(Collection $conversions, int $from, int $to, ?int $ingredientId): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        $direct = $this->pickConversion($conversions->get($from, collect()), $to, $ingredientId);
        if ($direct !== null) {
            return (float) $direct->factor;
        }

        $inverse = $this->pickConversion($conversions->get($to, collect()), $from, $ingredientId);
        if ($inverse !== null && (float) $inverse->factor > 0) {
            return 1 / (float) $inverse->factor;
        }

        return null;
    }

    private function pickConversion($fromGroup, int $to, ?int $ingredientId)
    {
        $candidates = collect($fromGroup)->where('to_unit_id', $to);

        if ($ingredientId) {
            $specific = $candidates->first(fn ($c) => (int) $c->ingredient_id === $ingredientId);
            if ($specific) {
                return $specific;
            }
        }

        return $candidates->first(fn ($c) => $c->ingredient_id === null);
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
