<?php

namespace App\Services\RecipeSuggestions;

use App\Exceptions\RecipeAvailability\RecipeAvailabilityException;
use App\Repositories\RecipeSuggestions\RecipeSuggestionsRepository;
use App\User;
use App\Services\RecipeAvailability\RecipeAvailabilityService;
use Illuminate\Support\Collection;

class RecipeSuggestionsService
{
    const STATUS_POSSIBLE        = 'possible';
    const STATUS_ALMOST_POSSIBLE = 'almost_possible';
    const STATUS_NOT_POSSIBLE    = 'not_possible';

    private RecipeSuggestionsRepository $repo;
    private RecipeAvailabilityService $availabilityService;

    public function __construct(RecipeSuggestionsRepository $repo, RecipeAvailabilityService $availabilityService)
    {
        $this->repo = $repo;
        $this->availabilityService = $availabilityService;
    }

    public function available(User $user, int $groupId, int $page, int $perPage): array
    {
        $this->assertMember($user, $groupId);
        $result = $this->computeAll($user, $groupId);
        $filtered = $result->filter(fn ($r) => $r['availability'] === self::STATUS_POSSIBLE)
            ->sortBy(function ($r) { return sprintf('%05d-%06.2f-%06d-%s', 99999 - $r['expiring_ingredients_count'], 100 - $r['coverage_percentage'], $r['total_time'], $r['name']); })->values();
        return $this->paginate($filtered, $page, $perPage);
    }

    public function almostAvailable(User $user, int $groupId, int $page, int $perPage): array
    {
        $this->assertMember($user, $groupId);
        $result = $this->computeAll($user, $groupId);
        $filtered = $result->filter(fn ($r) => $r['availability'] === self::STATUS_ALMOST_POSSIBLE && $r['missing_ingredients_count'] <= 2)->values();
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
        $batch     = $this->availabilityService->availabilityBatch($recipes, $groupId);

        $scored = $recipes->map(function ($recipe) use ($expiringIds, $batch) {
            $expiringMatch = collect($recipe->ingredients ?? [])
                ->filter(fn ($ri) => in_array((int) $ri->ingredient_id, $expiringIds, true))
                ->count();

            if ($expiringMatch === 0) {
                return null;
            }

            $avail = $batch[$recipe->id];

            return array_merge($this->recipeData($recipe), [
                'availability'          => $avail['status'],
                'max_possible_servings' => $avail['max_possible_servings'],
                'coverage_percentage'   => $avail['coverage_percentage'],
                'missing_ingredients_count' => $avail['missing_ingredients_count'],
                'expiring_ingredients'  => $expiringMatch,
                'score'                 => $expiringMatch,
                'reasons'               => ['uses_expiring_stock'],
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

        $expiringIds = $groupId !== null ? $this->repo->expiringIngredientIds($groupId) : [];
        $batch       = $groupId !== null ? $this->availabilityService->availabilityBatch($recipes, $groupId) : [];

        $scored = $recipes->map(function ($recipe) use ($batch, $expiringIds, $groupId) {
            $score   = 0;
            $reasons = [];

            if ($groupId !== null) {
                $status = $batch[$recipe->id]['status'];
                if ($status === RecipeAvailabilityService::STATUS_POSSIBLE) {
                    $score += 3;
                    $reasons[] = 'available_with_stock';
                } elseif ($status === RecipeAvailabilityService::STATUS_ALMOST_POSSIBLE) {
                    $score += 1;
                    $reasons[] = 'almost_available';
                }

                $expiringMatch = collect($recipe->ingredients ?? [])
                    ->filter(fn ($ri) => in_array((int) $ri->ingredient_id, $expiringIds, true))
                    ->count();
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
                'availability' => $groupId !== null ? $batch[$recipe->id]['status'] : null,
                'score'        => $score,
                'reasons'      => $reasons,
            ]);
        })->sortByDesc('score')->values();

        return $this->paginate($scored, $page, $perPage);
    }

    private function computeAll(User $user, int $groupId): Collection
    {
        $canManage = $user->hasPermission('recipes.manage');
        $recipes   = $this->repo->candidateRecipes($user->id, $canManage);
        $expiringIds = $this->repo->expiringIngredientIds($groupId);
        $batch     = $this->availabilityService->availabilityBatch($recipes, $groupId);

        return $recipes->map(function ($recipe) use ($expiringIds, $batch) {
            $avail = $batch[$recipe->id];
            $expiring = collect($recipe->ingredients ?? [])
                ->filter(fn ($item) => in_array((int) $item->ingredient_id, $expiringIds, true))
                ->count();

            return array_merge($this->recipeData($recipe), [
                'availability'          => $avail['status'], 'availability_status' => $avail['status'], 'can_cook' => $avail['can_cook'],
                'max_possible_servings' => $avail['max_possible_servings'], 'coverage_percentage' => $avail['coverage_percentage'],
                'required_ingredients_count' => $avail['required_ingredients_count'], 'available_ingredients_count' => $avail['available_ingredients_count'],
                'missing_ingredients_count' => $avail['missing_ingredients_count'], 'expiring_ingredients_count' => $expiring,
            ]);
        });
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
            'total_time'        => (int) ($recipe->prep_time_minutes ?? 0) + (int) ($recipe->cook_time_minutes ?? 0),
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
