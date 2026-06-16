<?php

namespace App\Services\RecipeCost;

use App\AuditLog;
use App\Exceptions\RecipeCost\RecipeCostException;
use App\Repositories\RecipeCost\RecipeCostRepository;
use App\User;
use Illuminate\Support\Facades\DB;

class RecipeCostService
{
    private RecipeCostRepository $repo;

    public function __construct(RecipeCostRepository $repo)
    {
        $this->repo = $repo;
    }

    public function show(User $user, $recipeId, ?int $familyGroupId): array
    {
        try {
            $recipe = $this->repo->findActiveOrFail($recipeId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw RecipeCostException::recipeNotFound();
        }

        if (!$recipe->is_public
            && $recipe->owner_user_id !== $user->id
            && !$user->hasPermission('recipes.manage')
        ) {
            throw RecipeCostException::recipeNotVisible();
        }

        if ($familyGroupId !== null) {
            $group = $this->repo->findFamilyGroup($familyGroupId);
            if (!$group) {
                throw RecipeCostException::familyGroupNotFound();
            }
            if (!$this->repo->isFamilyMember($familyGroupId, $user->id)) {
                throw RecipeCostException::familyGroupAccessDenied();
            }
        }

        return $this->calculate($recipe, $familyGroupId);
    }

    public function recalculate(User $user, $recipeId, string $ip, string $userAgent): array
    {
        if (!$user->hasPermission('recipes.manage')) {
            throw RecipeCostException::forbidden();
        }

        try {
            $recipe = $this->repo->findWithTrashedOrFail($recipeId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw RecipeCostException::recipeNotFound();
        }

        return DB::transaction(function () use ($user, $recipe, $ip, $userAgent) {
            $result = $this->calculate($recipe, null);

            $snapshot = $this->repo->insertSnapshot([
                'recipe_id'                  => $recipe->id,
                'estimated_total_cost'       => $result['total_cost'],
                'estimated_cost_per_serving' => $result['cost_per_serving'],
                'calculated_at'              => now(),
            ]);

            AuditLog::create([
                'user_id'     => $user->id,
                'action'      => 'recipe-cost.recalculated',
                'entity_name' => 'recipe_cost_snapshots',
                'entity_id'   => $snapshot->id,
                'old_values'  => null,
                'new_values'  => json_encode([
                    'estimated_total_cost'       => $result['total_cost'],
                    'estimated_cost_per_serving' => $result['cost_per_serving'],
                    'calculation_status'         => $result['calculation_status'],
                ]),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            $result['snapshot_id'] = $snapshot->id;
            return $result;
        });
    }

    private function calculate($recipe, ?int $familyGroupId): array
    {
        $recipe = $this->repo->loadIngredientsForCost($recipe->id);
        $ingredients = $recipe->ingredients;

        if ($ingredients->isEmpty()) {
            return $this->emptyResult($recipe, 'no_ingredients');
        }

        $totalCost    = 0.0;
        $missingPrice = 0;
        $currencies   = [];
        $breakdown    = [];

        foreach ($ingredients as $ri) {
            $ingredientId = $ri->ingredient_id;
            $ingredient   = $ri->ingredient;
            $recipeQty    = (float) $ri->quantity;
            $recipeUnitId = (int) $ri->unit_id;

            $priceData = $ri->specific_product_id
                ? $this->priceFromProduct($ri->specific_product_id, $familyGroupId, $recipeUnitId, $ingredientId)
                : $this->priceFromIngredient($ingredientId, $familyGroupId, $recipeUnitId);

            if ($priceData === null) {
                $missingPrice++;
                $breakdown[] = [
                    'ingredient_id'   => $ingredientId,
                    'ingredient_name' => $ingredient ? $ingredient->name : null,
                    'quantity'        => $recipeQty,
                    'unit_id'         => $recipeUnitId,
                    'line_cost'       => null,
                    'has_price'       => false,
                ];
                continue;
            }

            $lineCost = $recipeQty * $priceData['price_per_recipe_unit'];

            if ($priceData['currency']) {
                $currencies[] = $priceData['currency'];
            }

            $totalCost += $lineCost;

            $breakdown[] = [
                'ingredient_id'   => $ingredientId,
                'ingredient_name' => $ingredient ? $ingredient->name : null,
                'quantity'        => $recipeQty,
                'unit_id'         => $recipeUnitId,
                'line_cost'       => round($lineCost, 2),
                'has_price'       => true,
            ];
        }

        $uniqueCurrencies = array_unique($currencies);
        $currency = count($uniqueCurrencies) === 1 ? $uniqueCurrencies[0] : (count($uniqueCurrencies) > 1 ? 'MIXED' : null);

        $servings    = ($recipe->servings !== null && $recipe->servings > 0) ? (int) $recipe->servings : null;
        $perServing  = $servings !== null ? round($totalCost / $servings, 2) : null;
        $status      = $missingPrice === 0 ? 'complete' : 'partial';

        return [
            'recipe_id'          => $recipe->id,
            'servings'           => $servings,
            'total_cost'         => round($totalCost, 2),
            'cost_per_serving'   => $perServing,
            'currency'           => $currency,
            'calculation_status' => $status,
            'calculated_at'      => now()->toIso8601String(),
            'ingredients'        => $breakdown,
        ];
    }

    private function priceFromProduct(int $productId, ?int $familyGroupId, int $recipeUnitId, ?int $ingredientId): ?array
    {
        if ($familyGroupId !== null) {
            $stockData = $this->repo->stockPriceForProduct($familyGroupId, $productId);
            if ($stockData) {
                $factor = $this->repo->findConversion($recipeUnitId, $stockData['unit_id'], $ingredientId);
                if ($factor !== null) {
                    return [
                        'price_per_recipe_unit' => $stockData['price_per_unit'] * $factor,
                        'currency'              => null,
                    ];
                }
            }
        }

        $priceRecord = $this->repo->cheapestPriceForProduct($productId);
        if (!$priceRecord) {
            return null;
        }

        $product = $priceRecord->supermarketProduct->product;
        return $this->pricePerRecipeUnit($priceRecord, $product, $recipeUnitId, $ingredientId);
    }

    private function priceFromIngredient(int $ingredientId, ?int $familyGroupId, int $recipeUnitId): ?array
    {
        if ($familyGroupId !== null) {
            $stockData = $this->repo->stockPriceForIngredient($familyGroupId, $ingredientId);
            if ($stockData) {
                $factor = $this->repo->findConversion($recipeUnitId, $stockData['unit_id'], $ingredientId);
                if ($factor !== null) {
                    return [
                        'price_per_recipe_unit' => $stockData['price_per_unit'] * $factor,
                        'currency'              => null,
                    ];
                }
            }
        }

        $data = $this->repo->cheapestPriceForIngredient($ingredientId);
        if (!$data) {
            return null;
        }

        $priceRecord = $this->repo->cheapestPriceForIngredient($ingredientId);
        if (!$priceRecord) {
            return null;
        }

        // Re-fetch the actual SupermarketProductPrice object for product info
        $spPrice = \App\SupermarketProductPrice::whereHas('supermarketProduct.product', function ($q) use ($ingredientId) {
            $q->where('ingredient_id', $ingredientId)->where('status', 'active');
        })
            ->whereHas('supermarketProduct', function ($q) {
                $q->where('status', 'active');
            })
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>', now());
            })
            ->with('supermarketProduct.product')
            ->orderBy('price')
            ->first();

        if (!$spPrice) {
            return null;
        }

        $product = $spPrice->supermarketProduct->product;
        return $this->pricePerRecipeUnit($spPrice, $product, $recipeUnitId, $ingredientId);
    }

    private function pricePerRecipeUnit($priceRecord, $product, int $recipeUnitId, ?int $ingredientId): ?array
    {
        $netQty      = $product ? (float) $product->net_quantity : null;
        $pkgUnitId   = $product ? (int) $product->package_unit_id : null;

        if (!$netQty || !$pkgUnitId || $netQty <= 0) {
            return null;
        }

        $factor = $this->repo->findConversion($recipeUnitId, $pkgUnitId, $ingredientId);
        if ($factor === null) {
            return null;
        }

        $pricePerPkgUnit   = (float) $priceRecord->price / $netQty;
        $pricePerRecipeUnit = $pricePerPkgUnit * $factor;

        return [
            'price_per_recipe_unit' => $pricePerRecipeUnit,
            'currency'              => $priceRecord->currency,
        ];
    }

    private function emptyResult($recipe, string $status): array
    {
        return [
            'recipe_id'          => $recipe->id,
            'servings'           => $recipe->servings,
            'total_cost'         => 0.0,
            'cost_per_serving'   => null,
            'currency'           => null,
            'calculation_status' => $status,
            'calculated_at'      => now()->toIso8601String(),
            'ingredients'        => [],
        ];
    }
}
