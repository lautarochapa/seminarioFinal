<?php

namespace App\Services\RecipeSuggestions;

use App\Exceptions\RecipeAvailability\RecipeAvailabilityException;
use App\Repositories\RecipeSuggestions\RecipeSuggestionsRepository;
use App\User;
use Illuminate\Support\Collection;

class RecipeSuggestionsService
{
    const STATUS_POSSIBLE        = 'possible';
    const STATUS_ALMOST_POSSIBLE = 'almost_possible';
    const STATUS_NOT_POSSIBLE    = 'not_possible';

    private RecipeSuggestionsRepository $repo;

    public function __construct(RecipeSuggestionsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function available(User $user, int $groupId, int $page, int $perPage): array
    {
        $this->assertMember($user, $groupId);
        $result = $this->computeAll($user, $groupId);
        $filtered = $result->filter(fn ($r) => $r['availability'] === self::STATUS_POSSIBLE)->values();
        return $this->paginate($filtered, $page, $perPage);
    }

    public function almostAvailable(User $user, int $groupId, int $page, int $perPage): array
    {
        $this->assertMember($user, $groupId);
        $result = $this->computeAll($user, $groupId);
        $filtered = $result->filter(fn ($r) => $r['availability'] === self::STATUS_ALMOST_POSSIBLE)->values();
        return $this->paginate($filtered, $page, $perPage);
    }

    public function byExpiringStock(User $user, int $groupId, int $days, int $page, int $perPage): array
    {
        $this->assertMember($user, $groupId);
        $expiringIds = $this->repo->expiringIngredientIds($groupId, $days);

        if (empty($expiringIds)) {
            return $this->paginate(collect(), $page, $perPage);
        }

        $canManage = $user->hasPermission('recipes.manage');
        $recipes   = $this->repo->candidateRecipes($user->id, $canManage);
        $stockMap  = $this->repo->stockSummary($groupId);
        $convs     = $this->repo->allConversions();

        $scored = $recipes->map(function ($recipe) use ($expiringIds, $stockMap, $convs) {
            $ingredients    = $recipe->ingredients ?? collect();
            $expiringMatch  = 0;

            foreach ($ingredients as $ri) {
                if (in_array((int) $ri->ingredient_id, $expiringIds, true)) {
                    $expiringMatch++;
                }
            }

            if ($expiringMatch === 0) {
                return null;
            }

            $avail = $this->recipeAvailability($recipe, $stockMap, $convs);

            return array_merge($this->recipeData($recipe), [
                'availability'        => $avail['status'],
                'max_possible_servings' => $avail['max_servings'],
                'expiring_ingredients'=> $expiringMatch,
                'score'               => $expiringMatch,
                'reasons'             => ['uses_expiring_stock'],
            ]);
        })->filter()->sortByDesc('score')->values();

        return $this->paginate($scored, $page, $perPage);
    }

    public function byBudget(User $user, int $groupId, ?float $maxCost, int $page, int $perPage): array
    {
        $this->assertMember($user, $groupId);

        if ($maxCost === null) {
            $budget = $this->repo->currentBudget($groupId);
            if ($budget) {
                $maxCost = (float) $budget->total_amount;
            }
        }

        if ($maxCost === null) {
            return $this->paginate(collect(), $page, $perPage, ['message' => 'No hay presupuesto activo para este grupo en el mes actual.']);
        }

        $allowedIds = $this->repo->recipeIdsByMaxCost($groupId, $maxCost);

        $canManage = $user->hasPermission('recipes.manage');
        $recipes   = $this->repo->candidateRecipes($user->id, $canManage)
            ->filter(fn ($r) => in_array($r->id, $allowedIds, true))
            ->values();

        $result = $recipes->map(function ($recipe) {
            return array_merge($this->recipeData($recipe), [
                'availability' => null,
                'reasons'      => ['within_budget'],
            ]);
        });

        return $this->paginate($result, $page, $perPage);
    }

    public function byObjectives(User $user, int $groupId, int $page, int $perPage): array
    {
        $this->assertMember($user, $groupId);

        // No structured link from user objectives/dietary_restrictions/allergies
        // to recipe ingredients exists in the current schema.
        // Returning all visible active recipes as a passthrough.
        $canManage = $user->hasPermission('recipes.manage');
        $recipes   = $this->repo->candidateRecipes($user->id, $canManage);

        $result = $recipes->map(function ($recipe) {
            return array_merge($this->recipeData($recipe), [
                'availability' => null,
                'reasons'      => ['matches_objectives'],
            ]);
        });

        return $this->paginate($result, $page, $perPage, [
            'objectives_filtering' => 'partial',
            'note'                 => 'Objective-based filtering not available: no ingredient-objective link in current schema.',
        ]);
    }

    public function suggestions(User $user, ?int $groupId, int $page, int $perPage): array
    {
        if ($groupId !== null) {
            $group = $this->repo->findFamilyGroup($groupId);
            if (!$group) {
                throw RecipeAvailabilityException::familyGroupNotFound();
            }
            if (!$this->repo->isMember($groupId, $user->id)) {
                throw RecipeAvailabilityException::familyGroupAccessDenied();
            }
        }

        $canManage = $user->hasPermission('recipes.manage');
        $recipes   = $this->repo->candidateRecipes($user->id, $canManage);

        $stockMap     = $groupId !== null ? $this->repo->stockSummary($groupId) : [];
        $convs        = $this->repo->allConversions();
        $expiringIds  = $groupId !== null ? $this->repo->expiringIngredientIds($groupId) : [];

        $scored = $recipes->map(function ($recipe) use ($stockMap, $convs, $expiringIds, $groupId) {
            $score   = 0;
            $reasons = [];

            if ($groupId !== null && count($stockMap) > 0) {
                $avail = $this->recipeAvailability($recipe, $stockMap, $convs);
                if ($avail['status'] === self::STATUS_POSSIBLE) {
                    $score += 3;
                    $reasons[] = 'available_with_stock';
                } elseif ($avail['status'] === self::STATUS_ALMOST_POSSIBLE) {
                    $score += 1;
                    $reasons[] = 'almost_available';
                }

                $expiringMatch = 0;
                foreach ($recipe->ingredients as $ri) {
                    if (in_array((int) $ri->ingredient_id, $expiringIds, true)) {
                        $expiringMatch++;
                    }
                }
                if ($expiringMatch > 0) {
                    $score += $expiringMatch;
                    $reasons[] = 'uses_expiring_stock';
                }
            }

            if ($recipe->is_official) {
                $score += 1;
                $reasons[] = 'official_recipe';
            }

            return array_merge($this->recipeData($recipe), [
                'score'   => $score,
                'reasons' => $reasons,
            ]);
        })->sortByDesc('score')->values();

        return $this->paginate($scored, $page, $perPage);
    }

    private function computeAll(User $user, int $groupId): Collection
    {
        $canManage = $user->hasPermission('recipes.manage');
        $recipes   = $this->repo->candidateRecipes($user->id, $canManage);
        $stockMap  = $this->repo->stockSummary($groupId);
        $convs     = $this->repo->allConversions();

        return $recipes->map(function ($recipe) use ($stockMap, $convs) {
            $avail = $this->recipeAvailability($recipe, $stockMap, $convs);
            return array_merge($this->recipeData($recipe), [
                'availability'          => $avail['status'],
                'max_possible_servings' => $avail['max_servings'],
                'coverage_percentage'   => $avail['coverage_pct'],
            ]);
        });
    }

    private function recipeAvailability($recipe, array $stockMap, Collection $convs): array
    {
        $ingredients     = $recipe->ingredients ?? collect();
        $requiredServings = ($recipe->servings !== null && $recipe->servings > 0) ? (int) $recipe->servings : 1;

        if ($ingredients->isEmpty()) {
            return ['status' => self::STATUS_NOT_POSSIBLE, 'max_servings' => 0, 'coverage_pct' => 0.0];
        }

        $maxServings = PHP_INT_MAX;
        $totalReq    = 0;
        $totalAvail  = 0;

        foreach ($ingredients as $ri) {
            $ingredientId = (int) $ri->ingredient_id;
            $requiredQty  = (float) $ri->quantity;
            $recipeUnitId = (int) $ri->unit_id;
            $perServing   = $requiredQty / $requiredServings;

            $ingStock     = $stockMap[$ingredientId] ?? [];
            $availableQty = 0.0;

            foreach ($ingStock as $stockUnitId => $stockQty) {
                $factor = $this->findConversionFactor($convs, $stockUnitId, $recipeUnitId, $ingredientId);
                if ($factor !== null) {
                    $availableQty += $stockQty * $factor;
                }
            }

            $maxFromThis = $perServing > 0 ? (int) floor($availableQty / $perServing) : ($availableQty >= $requiredQty ? PHP_INT_MAX : 0);
            $maxServings = min($maxServings, $maxFromThis);

            $totalReq   += $requiredQty;
            $totalAvail += min($availableQty, $requiredQty);
        }

        if ($maxServings === PHP_INT_MAX) {
            $maxServings = $requiredServings;
        }

        $status      = $maxServings >= $requiredServings ? self::STATUS_POSSIBLE : ($maxServings > 0 ? self::STATUS_ALMOST_POSSIBLE : self::STATUS_NOT_POSSIBLE);
        $coveragePct = $totalReq > 0 ? round(min(100.0, ($totalAvail / $totalReq) * 100), 1) : 0.0;

        return ['status' => $status, 'max_servings' => $maxServings, 'coverage_pct' => $coveragePct];
    }

    private function findConversionFactor(Collection $convs, int $fromUnit, int $toUnit, int $ingredientId): ?float
    {
        if ($fromUnit === $toUnit) {
            return 1.0;
        }

        $candidates = $convs->get($fromUnit, collect())->where('to_unit_id', $toUnit);
        $specific   = $candidates->first(fn ($c) => (int) $c->ingredient_id === $ingredientId);
        if ($specific) {
            return (float) $specific->factor;
        }
        $generic = $candidates->first(fn ($c) => $c->ingredient_id === null);
        return $generic ? (float) $generic->factor : null;
    }

    private function recipeData($recipe): array
    {
        return [
            'id'                => $recipe->id,
            'name'              => $recipe->name,
            'difficulty'        => $recipe->difficulty,
            'prep_time_minutes' => $recipe->prep_time_minutes,
            'cook_time_minutes' => $recipe->cook_time_minutes,
            'servings'          => $recipe->servings,
            'is_official'       => $recipe->is_official,
            'source_type'       => $recipe->source_type,
        ];
    }

    private function assertMember(User $user, int $groupId): void
    {
        $group = $this->repo->findFamilyGroup($groupId);
        if (!$group) {
            throw RecipeAvailabilityException::familyGroupNotFound();
        }
        if (!$this->repo->isMember($groupId, $user->id)) {
            throw RecipeAvailabilityException::familyGroupAccessDenied();
        }
    }

    private function paginate(Collection $items, int $page, int $perPage, array $meta = []): array
    {
        $total  = $items->count();
        $offset = ($page - 1) * $perPage;
        $sliced = $items->slice($offset, $perPage)->values();

        return [
            'data' => $sliced,
            'meta' => array_merge([
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
            ], $meta),
        ];
    }
}
