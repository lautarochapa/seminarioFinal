<?php

namespace App\Services\RecipeFavoritesCooked;

use App\AuditLog;
use App\Exceptions\RecipeFavoritesCooked\RecipeFavoritesCookedException;
use App\Repositories\RecipeFavoritesCooked\RecipeFavoritesCookedRepository;
use App\Services\RecipeAvailability\RecipeAvailabilityService;
use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecipeFavoritesCookedService
{
    private RecipeFavoritesCookedRepository $repo;
    private RecipeAvailabilityService $availability;

    public function __construct(RecipeFavoritesCookedRepository $repo, RecipeAvailabilityService $availability)
    {
        $this->repo = $repo;
        $this->availability = $availability;
    }

    public function addFavorite(User $user, int $recipeId, string $ip, string $userAgent): void
    {
        $recipe = $this->repo->findVisible($recipeId, $user->id);
        if (!$recipe) {
            throw RecipeFavoritesCookedException::recipeNotFound();
        }

        if ($this->repo->findFavorite($user->id, $recipeId)) {
            throw RecipeFavoritesCookedException::alreadyFavorited();
        }

        $fav = $this->repo->createFavorite($user->id, $recipeId);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'recipe_favorite_added',
            'entity_name'=> 'recipe_favorites',
            'entity_id'  => $fav->id,
            'old_values' => null,
            'new_values' => ['recipe_id' => $recipeId],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    public function removeFavorite(User $user, int $recipeId, string $ip, string $userAgent): void
    {
        $fav = $this->repo->findFavorite($user->id, $recipeId);
        if (!$fav) {
            throw RecipeFavoritesCookedException::notFavorited();
        }

        $favId = $fav->id;
        $this->repo->deleteFavorite($fav);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'recipe_favorite_removed',
            'entity_name'=> 'recipe_favorites',
            'entity_id'  => $favId,
            'old_values' => ['recipe_id' => $recipeId],
            'new_values' => null,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    public function listFavorites(User $user, int $page, int $perPage): array
    {
        return ['paginator' => $this->repo->paginateFavorites($user->id, $page, $perPage)];
    }

    public function cook(User $user, int $recipeId, array $input, string $ip, string $userAgent): array
    {
        $recipe = $this->repo->findVisible($recipeId, $user->id);
        if (!$recipe) {
            throw RecipeFavoritesCookedException::recipeNotFound();
        }

        $servings      = (int) $input['servings'];
        $familyGroupId = isset($input['family_group_id']) ? (int) $input['family_group_id'] : null;
        $deductStock   = !empty($input['deduct_stock']);
        $idempotencyKey = ! empty($input['idempotency_key']) ? trim((string) $input['idempotency_key']) : null;
        $cacheKey = null;

        if ($familyGroupId !== null) {
            $group = $this->repo->findFamilyGroup($familyGroupId);
            if (!$group) {
                throw RecipeFavoritesCookedException::familyGroupNotFound();
            }
            if (!$this->repo->isFamilyMember($familyGroupId, $user->id)) {
                throw RecipeFavoritesCookedException::familyGroupAccessDenied();
            }
        }

        if ($idempotencyKey) {
            $cacheKey = 'recipe_cook:' . $user->id . ':' . $recipeId . ':' . sha1($idempotencyKey);
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['cook_log_id'])) {
                return $cached;
            }
            if (! Cache::add($cacheKey, ['processing' => true], now()->addMinutes(10))) {
                throw RecipeFavoritesCookedException::cookInProgress();
            }
        }

        try {
            if ($deductStock) {
                if ($familyGroupId === null) {
                    throw new RecipeFavoritesCookedException('FAMILY_GROUP_REQUIRED', 'Seleccioná el grupo familiar del que querés descontar los ingredientes.', 422);
                }
                $availability = $this->availability->availability($user, $recipeId, $familyGroupId, $servings);
                if (empty($availability['can_cook'])) {
                    foreach ($availability['ingredients'] as $ingredient) {
                        if ($ingredient['status'] === 'missing') {
                            throw RecipeFavoritesCookedException::ingredientMissing($ingredient);
                        }
                    }
                    throw RecipeFavoritesCookedException::insufficientStock();
                }
            }

            $transactionResult = DB::transaction(function () use ($user, $recipe, $recipeId, $servings, $familyGroupId, $deductStock, $ip, $userAgent) {
            $log = $this->repo->createCookLog([
                'user_id'          => $user->id,
                'family_group_id'  => $familyGroupId,
                'recipe_id'        => $recipeId,
                'servings'         => $servings,
                'cooked_at'        => now(),
                'stock_discounted' => false,
                'notes'            => null,
            ]);

            $movementsCreated = 0;
            if ($deductStock && $familyGroupId !== null) {
                $movementsCreated = $this->deductStock($log->id, $recipe, $servings, $familyGroupId, $user->id, $recipeId);
            }

            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => 'recipe_cooked',
                'entity_name'=> 'recipe_cook_logs',
                'entity_id'  => $log->id,
                'old_values' => null,
                'new_values' => [
                    'recipe_id'       => $recipeId,
                    'servings'        => $servings,
                    'family_group_id' => $familyGroupId,
                    'stock_discounted'=> $deductStock && $familyGroupId !== null,
                ],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            return ['cook_log_id' => $log->id, 'stock_movements_created' => $movementsCreated];
            });
        } catch (\Throwable $e) {
            if ($cacheKey) {
                Cache::forget($cacheKey);
            }
            throw $e;
        }

        $result = array_merge($transactionResult, ['recipe_id' => $recipeId, 'servings' => $servings, 'family_group_id' => $familyGroupId, 'stock_discounted' => $deductStock && $familyGroupId !== null]);
        if ($cacheKey) {
            Cache::put($cacheKey, $result, now()->addMinutes(10));
        }

        return $result;
    }

    public function listCooked(User $user, int $page, int $perPage): array
    {
        return ['paginator' => $this->repo->paginateCookLogs($user->id, $page, $perPage)];
    }

    private function deductStock(int $logId, $recipe, int $servings, int $familyGroupId, int $userId, int $recipeId): int
    {
        $recipeWithIng = $this->repo->loadRecipeWithIngredients($recipe->id);
        $baseServings  = ($recipeWithIng->servings !== null && $recipeWithIng->servings > 0)
            ? (int) $recipeWithIng->servings
            : 1;

        $deductions = [];

        foreach ($recipeWithIng->ingredients as $ri) {
            if ($ri->is_optional) {
                continue;
            }

            $ingredientId  = (int) $ri->ingredient_id;
            $recipeUnitId  = (int) $ri->unit_id;
            $specificProductId = $ri->specific_product_id ? (int) $ri->specific_product_id : null;
            $totalRequired = ((float) $ri->quantity / $baseServings) * $servings;

            $stockItems    = $this->repo->stockItemsForIngredient($familyGroupId, $ingredientId, $specificProductId);
            if (empty($stockItems)) {
                $name = $ri->ingredient ? $ri->ingredient->name : null;
                throw RecipeFavoritesCookedException::ingredientMissing([
                    'ingredient_id' => $ingredientId,
                    'ingredient_name' => $name,
                    'required_quantity' => $totalRequired,
                    'unit_id' => $recipeUnitId,
                ]);
            }
            $remaining     = $totalRequired;
            $plan          = [];

            foreach ($stockItems as $item) {
                if ($remaining <= 0) {
                    break;
                }

                $stockUnitId = (int) $item->unit_id;
                $stockQty    = (float) $item->quantity;

                $factor = $this->repo->findConversionFactor($stockUnitId, $recipeUnitId, $ingredientId);
                if ($factor === null) {
                    continue;
                }

                $stockInRecipeUnit = $stockQty * $factor;
                $toDeductRecipeUnit = min($remaining, $stockInRecipeUnit);
                $toDeductStockUnit  = $toDeductRecipeUnit / $factor;

                $plan[]    = ['item_id' => (int) $item->id, 'deduct' => $toDeductStockUnit, 'unit_id' => $stockUnitId, 'product_id' => (int) $item->product_id];
                $remaining -= $toDeductRecipeUnit;
            }

            if ($remaining > 0.0001) {
                throw RecipeFavoritesCookedException::insufficientStock();
            }

            $deductions = array_merge($deductions, $plan);
        }

        foreach ($deductions as $d) {
            if (! $this->repo->deductStockItem($d['item_id'], $d['deduct'])) {
                throw RecipeFavoritesCookedException::insufficientStock();
            }
            $this->repo->createStockMovement([
                'family_group_id'    => $familyGroupId,
                'stock_item_id'      => $d['item_id'],
                'product_id'         => $d['product_id'],
                'movement_type'      => 'consumption',
                'quantity'           => $d['deduct'],
                'unit_id'            => $d['unit_id'],
                'reason'             => 'recipe_cook',
                'related_recipe_id'  => $recipeId,
                'created_by'         => $userId,
            ]);
        }

        $this->repo->markCookLogDiscounted($logId);
        return count($deductions);
    }
}
