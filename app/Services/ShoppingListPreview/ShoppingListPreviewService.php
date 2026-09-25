<?php

namespace App\Services\ShoppingListPreview;

use App\AuditLog;
use App\MealPlan;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Exceptions\MealPlanItems\MealPlanItemException;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ShoppingListPreview\ShoppingListPreviewRepository;
use App\Services\RecipeShoppingList\RecipePriceEstimator;
use App\Services\ShoppingLists\ShoppingListGenerationGuard;
use App\Services\ShoppingLists\ShoppingListTotalService;
use App\ShoppingList;
use App\User;
use Illuminate\Support\Facades\DB;

class ShoppingListPreviewService
{
    private $groups;
    private $repo;
    private $priceEstimator;
    private $totals;
    private $generationGuard;

    public function __construct(
        FamilyGroupRepository $groups,
        ShoppingListPreviewRepository $repo,
        RecipePriceEstimator $priceEstimator,
        ShoppingListTotalService $totals,
        ShoppingListGenerationGuard $generationGuard
    ) {
        $this->groups = $groups;
        $this->repo = $repo;
        $this->priceEstimator = $priceEstimator;
        $this->totals = $totals;
        $this->generationGuard = $generationGuard;
    }

    public function preview(User $user, int $groupId, int $planId): array
    {
        $this->assertMember($user, $groupId);
        $plan = $this->repo->findPlanForGroup($planId, $groupId);

        if (!$plan) {
            throw MealPlanItemException::planNotFound();
        }

        $requirements = $this->requirements($plan);
        return $this->subtractStock($groupId, $requirements);
    }

    public function generate(User $user, int $groupId, int $planId, string $ip, string $ua): array
    {
        $items = $this->preview($user, $groupId, $planId);
        $items = $this->resolvePurchasableProducts($groupId, $items);

        return DB::transaction(function () use ($user, $groupId, $planId, $items, $ip, $ua) {
            // Serialize even when no reusable list exists, without blocking FK KEY SHARE.
            MealPlan::where('family_group_id', $groupId)->where('id', $planId)
                ->lock(DB::connection()->getDriverName() === 'pgsql' ? 'for no key update' : true)->firstOrFail();
            $existing = $this->repo->existingList($groupId, $planId);
            $created = false;

            if ($existing) {
                $list = $existing;
                $this->generationGuard->assertReusable($list);
                if ($list->status !== ShoppingList::STATUS_ACTIVE && $list->canTransitionTo(ShoppingList::STATUS_ACTIVE)) {
                    $list->status = ShoppingList::STATUS_ACTIVE;
                    $list->save();
                }
            } else {
                $list = $this->repo->createList([
                    'family_group_id' => $groupId,
                    'meal_plan_id' => $planId,
                    'created_by' => $user->id,
                    'source_type' => 'meal_plan',
                    'status' => ShoppingList::STATUS_ACTIVE,
                ]);
                $created = true;
            }

            $this->repo->replaceItems($list, $items);
            $this->totals->recalculate($list);
            $list = $this->repo->loadList($list);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'shopping_list.generated',
                'entity_name' => 'shopping_lists',
                'entity_id' => (string) $list->id,
                'old_values' => null,
                'new_values' => [
                    'family_group_id' => $groupId,
                    'meal_plan_id' => $planId,
                    'items_count' => count($items),
                    'created' => $created,
                ],
                'ip_address' => $ip,
                'user_agent' => $ua,
            ]);

            return ['list' => $list, 'created' => $created];
        });
    }

    private function assertMember(User $user, int $groupId): void
    {
        try {
            $this->groups->findOrFailForUser($groupId, $user->id);
        } catch (FamilyGroupException $e) {
            throw MealPlanItemException::groupNotFound();
        }
    }

    /**
     * Enriches each missing requirement with a purchasable product when one can be
     * resolved from the ingredient link and has a known package size. The missing
     * quantity (in the recipe's unit) is converted into a whole number of packages
     * priced in the product's own unit, so the generated list can be compared between
     * supermarkets. Requirements with no resolvable product are left untouched and keep
     * their ingredient + recipe-unit fallback.
     */
    private function resolvePurchasableProducts(int $groupId, array $items): array
    {
        foreach ($items as $index => $item) {
            $ingredientId = (int) $item['ingredient']['id'];
            $recipeUnitId = (int) $item['unit']['id'];
            $missingQty   = (float) $item['missing_quantity'];

            $product = $this->repo->resolveProductForIngredient($ingredientId, $groupId, $item['specific_product_id'] ?? null);
            if ($product) {
                $items[$index]['resolved_product_id'] = (int) $product->id;
                $items[$index]['purchase_quantity'] = $missingQty;
                $items[$index]['purchase_unit_id'] = $recipeUnitId;
                $items[$index]['estimated_price'] = null;
            }
            if (! $product || ! $product->net_quantity || ! $product->package_unit_id) {
                continue;
            }

            $packageUnitId = (int) $product->package_unit_id;
            $factor = $this->repo->conversionFactor($recipeUnitId, $packageUnitId, $ingredientId);
            if ($factor === null) {
                continue;
            }

            $neededInPackageUnit = $missingQty * $factor;
            $packages = max(1, (int) ceil($neededInPackageUnit / (float) $product->net_quantity));

            $packaging = app(\App\Services\Products\ProductPackagingService::class);
            $purchaseUnitId = $packaging->hasContent($product) ? $packaging->purchaseUnitId($product) : null;
            if ($purchaseUnitId === null) {
                $items[$index]['purchase_warning'] = 'Sin unidad Paquete activa o contenido valido: se conserva el faltante en su unidad original, sin precio estimado.';
                continue;
            }

            $price = $this->priceEstimator->resolve((int) $product->id, $groupId, null, null);

            $items[$index]['resolved_product_id'] = (int) $product->id;
            $items[$index]['purchase_quantity']   = $packages;
            $items[$index]['purchase_unit_id']    = $purchaseUnitId;
            $items[$index]['estimated_price']     = $price['unit_price'];
        }

        return $items;
    }

    private function requirements($plan): array
    {
        $requirements = [];

        foreach ($plan->items as $item) {
            if (!$item->recipe) {
                continue;
            }

            $servings = $this->servings($item);
            $baseServings = ($item->recipe->servings !== null && (float) $item->recipe->servings > 0)
                ? (float) $item->recipe->servings
                : 1.0;

            foreach ($item->recipe->ingredients as $recipeIngredient) {
                if ($recipeIngredient->is_optional || !$recipeIngredient->ingredient || !$recipeIngredient->unit) {
                    continue;
                }

                $key = $recipeIngredient->ingredient_id.'-'.$recipeIngredient->unit_id.'-'.$recipeIngredient->specific_product_id;
                $quantity = ((float) $recipeIngredient->quantity / $baseServings) * $servings;

                if (!isset($requirements[$key])) {
                    $requirements[$key] = [
                        'specific_product_id' => $recipeIngredient->specific_product_id ? (int) $recipeIngredient->specific_product_id : null,
                        'ingredient' => [
                            'id' => (int) $recipeIngredient->ingredient->id,
                            'name' => $recipeIngredient->ingredient->name,
                        ],
                        'unit' => [
                            'id' => (int) $recipeIngredient->unit->id,
                            'code' => $recipeIngredient->unit->code,
                            'symbol' => $recipeIngredient->unit->symbol,
                        ],
                        'required_quantity' => 0.0,
                        'recipe_sources' => [],
                        'incomplete' => false,
                    ];
                }

                $requirements[$key]['required_quantity'] += $quantity;
                $requirements[$key]['recipe_sources'][] = [
                    'meal_plan_item_id' => (int) $item->id,
                    'recipe_id' => (int) $item->recipe->id,
                    'recipe_name' => $item->recipe->name ?: $item->recipe->nombre,
                    'quantity' => $quantity,
                ];
            }
        }

        return array_values($requirements);
    }

    private function servings($item): float
    {
        if ($item->servings_total !== null && (float) $item->servings_total > 0) {
            return (float) $item->servings_total;
        }

        if ($item->recipe && $item->recipe->servings !== null && (float) $item->recipe->servings > 0) {
            return (float) $item->recipe->servings;
        }

        return 1.0;
    }

    private function subtractStock(int $groupId, array $requirements): array
    {
        $missing = [];
        $reserved = [];
        // Reserve constrained products first; each lot can cover only one shared budget.
        usort($requirements, function ($a, $b) {
            return (int) !empty($b['specific_product_id']) <=> (int) !empty($a['specific_product_id']);
        });

        foreach ($requirements as $requirement) {
            $remaining = (float) $requirement['required_quantity'];
            $ingredientId = (int) $requirement['ingredient']['id'];
            $unitId = (int) $requirement['unit']['id'];
            $stockItems = $this->repo->stockItemsForIngredient($groupId, $ingredientId, $requirement['specific_product_id'] ?? null);
            $incomplete = false;

            foreach ($stockItems as $stockItem) {
                if ($remaining <= 0) {
                    break;
                }

                $factor = $this->repo->conversionFactor((int) $stockItem['unit_id'], $unitId, $ingredientId);
                if ($factor === null || $factor <= 0) {
                    $incomplete = true;
                    continue;
                }

                $lotId = (int) $stockItem['id'];
                $available = max(0, (float) $stockItem['quantity'] - ($reserved[$lotId] ?? 0));
                $used = min($remaining, $available * $factor);
                $remaining -= $used;
                $reserved[$lotId] = ($reserved[$lotId] ?? 0) + $used / $factor;
            }

            if ($remaining > 0.0001) {
                $requirement['missing_quantity'] = round($remaining, 4);
                $requirement['incomplete'] = $requirement['incomplete'] || $incomplete;
                $missing[] = $requirement;
            }
        }

        return $missing;
    }
}
