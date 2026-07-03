<?php

namespace App\Services\RecipeShoppingList;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Exceptions\RecipeAvailability\RecipeAvailabilityException;
use App\Product;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\RecipeAvailability\RecipeAvailabilityRepository;
use App\Repositories\ShoppingListItems\ShoppingListItemRepository;
use App\Repositories\ShoppingLists\ShoppingListRepository;
use App\ShoppingList;
use App\SupermarketBranch;
use App\SupermarketChain;
use App\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class RecipeShoppingListService
{
    private $groups;
    private $recipeAvailability;
    private $lists;
    private $items;
    private $priceEstimator;

    public function __construct(
        FamilyGroupRepository $groups,
        RecipeAvailabilityRepository $recipeAvailability,
        ShoppingListRepository $lists,
        ShoppingListItemRepository $items,
        RecipePriceEstimator $priceEstimator
    ) {
        $this->groups = $groups;
        $this->recipeAvailability = $recipeAvailability;
        $this->lists = $lists;
        $this->items = $items;
        $this->priceEstimator = $priceEstimator;
    }

    public function generate(User $user, int $groupId, int $recipeId, array $data, string $ip, string $ua): array
    {
        $this->groups->findOrFailForUser($groupId, $user->id);
        $recipe = $this->resolveRecipe($user, $recipeId);

        $branchId = ! empty($data['supermarket_branch_id']) ? (int) $data['supermarket_branch_id'] : null;
        $chainId = ! empty($data['supermarket_chain_id']) ? (int) $data['supermarket_chain_id'] : null;
        $this->assertValidSupermarketSelection($branchId, $chainId);

        $baseServings = ($recipe->servings !== null && (float) $recipe->servings > 0) ? (float) $recipe->servings : 1.0;
        $targetServings = (isset($data['servings']) && (int) $data['servings'] > 0)
            ? (float) $data['servings']
            : $baseServings;
        $scale = $targetServings / $baseServings;

        $stockMap = $this->recipeAvailability->stockByIngredient($groupId);

        return DB::transaction(function () use ($user, $groupId, $recipe, $data, $scale, $stockMap, $branchId, $chainId, $ip, $ua) {
            $list = $this->resolveList($user, $groupId, $data);
            $created = $list->wasRecentlyCreated;
            $itemsAdded = 0;
            $itemsSkippedDuplicate = 0;
            $unmapped = [];
            $warnings = [];
            $priced = [];
            $estimatedTotal = 0.0;
            $itemsWithoutPrice = 0;

            foreach ($recipe->ingredients as $ri) {
                if ($ri->is_optional) {
                    continue;
                }

                if (! $ri->ingredient || ! $ri->unit) {
                    $unmapped[] = [
                        'ingredient_id'   => $ri->ingredient_id,
                        'ingredient_name' => $ri->ingredient ? $ri->ingredient->name : null,
                        'reason'          => 'INGREDIENT_OR_UNIT_MISSING',
                    ];
                    continue;
                }

                $unitId = (int) $ri->unit_id;
                $ingredientId = (int) $ri->ingredient_id;
                $specificProductId = $ri->specific_product_id ? (int) $ri->specific_product_id : null;
                $requiredQty = (float) $ri->quantity * $scale;

                if ($specificProductId) {
                    $productStock = $this->recipeAvailability->stockByProduct($groupId, $specificProductId);
                    [$availableQty, $incomplete] = $this->sumConvertedStock($productStock, $unitId, $ingredientId);
                } else {
                    $ingStock = $stockMap[$ingredientId] ?? [];
                    [$availableQty, $incomplete] = $this->sumConvertedStock($ingStock, $unitId, $ingredientId);
                }

                if ($incomplete) {
                    $warnings[] = "No se pudo convertir una unidad de stock para el ingrediente '{$ri->ingredient->name}'; se asumio disponibilidad parcial.";
                }

                $missingQty = round(max(0.0, $requiredQty - $availableQty), 4);

                if ($missingQty <= 0.0001) {
                    continue;
                }

                $resolvedProductId = $specificProductId ?? $this->resolveDefaultProduct($ingredientId);

                $purchaseQuantity = $missingQty;
                $purchaseUnitId = $unitId;
                $priceInfo = ['unit_price' => null, 'source' => null, 'updated_at' => null, 'branch_id' => null, 'chain_id' => null];
                $packages = null;

                if ($resolvedProductId) {
                    [$packages, $purchaseUnitId, $packagingWarning] = $this->resolvePackaging($resolvedProductId, $missingQty, $unitId, $ingredientId);
                    if ($packagingWarning) {
                        $warnings[] = $packagingWarning;
                    }
                    if ($packages !== null) {
                        $purchaseQuantity = $packages;
                        $priceInfo = $this->priceEstimator->resolve($resolvedProductId, $groupId, $branchId, $chainId);
                    }
                }

                if ($this->items->duplicateExists($list->id, $resolvedProductId ? null : $ingredientId, $resolvedProductId, $purchaseUnitId)) {
                    $itemsSkippedDuplicate++;
                    continue;
                }

                $item = $this->items->create([
                    'shopping_list_id' => $list->id,
                    'ingredient_id'    => $resolvedProductId ? null : $ingredientId,
                    'product_id'       => $resolvedProductId,
                    'quantity'         => $purchaseQuantity,
                    'unit_id'          => $purchaseUnitId,
                    'estimated_price'  => $priceInfo['unit_price'],
                    'status'           => 'pending',
                    'notes'            => 'Generado desde receta #' . $recipe->id,
                ]);

                $itemsAdded++;

                $subtotal = ($priceInfo['unit_price'] !== null) ? round($priceInfo['unit_price'] * $purchaseQuantity, 2) : null;
                if ($subtotal !== null) {
                    $estimatedTotal += $subtotal;
                } else {
                    $itemsWithoutPrice++;
                }

                $priced[] = [
                    'shopping_list_item_id' => $item->id,
                    'ingredient_id'         => $ingredientId,
                    'product_id'            => $resolvedProductId,
                    'requested_quantity'    => $missingQty,
                    'requested_unit_id'     => $unitId,
                    'purchase_quantity'     => $purchaseQuantity,
                    'purchase_unit_id'      => $purchaseUnitId,
                    'estimated_unit_price'  => $priceInfo['unit_price'],
                    'estimated_subtotal'    => $subtotal,
                    'price_source'          => $priceInfo['source'],
                    'price_updated_at'      => $priceInfo['updated_at'],
                    'supermarket_branch_id' => $priceInfo['branch_id'],
                    'supermarket_chain_id'  => $priceInfo['chain_id'],
                ];
            }

            AuditLog::create([
                'user_id'     => $user->id,
                'action'      => 'shopping_list.generated_from_recipe',
                'entity_name' => 'shopping_lists',
                'entity_id'   => (string) $list->id,
                'old_values'  => null,
                'new_values'  => [
                    'family_group_id' => $groupId,
                    'recipe_id'       => $recipe->id,
                    'items_added'     => $itemsAdded,
                    'created_list'    => $created,
                    'estimated_total' => round($estimatedTotal, 2),
                ],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return [
                'shopping_list'           => $this->lists->findInGroup($groupId, $list->id),
                'created'                 => $created,
                'items_added'             => $itemsAdded,
                'items_skipped_duplicate' => $itemsSkippedDuplicate,
                'unmapped_ingredients'    => $unmapped,
                'priced_items'            => $priced,
                'estimated_total'         => round($estimatedTotal, 2),
                'items_without_price'     => $itemsWithoutPrice,
                'warnings'                => $warnings,
            ];
        });
    }

    private function resolveRecipe(User $user, int $recipeId)
    {
        try {
            $recipe = $this->recipeAvailability->loadRecipeIngredients($recipeId);
        } catch (ModelNotFoundException $e) {
            throw RecipeAvailabilityException::recipeNotFound();
        }

        if (! $recipe->is_public
            && $recipe->owner_user_id !== $user->id
            && ! $user->hasPermission('recipes.manage')
        ) {
            throw RecipeAvailabilityException::recipeNotVisible();
        }

        return $recipe;
    }

    private function resolveList(User $user, int $groupId, array $data): ShoppingList
    {
        if (! empty($data['shopping_list_id'])) {
            $list = $this->lists->findInGroup($groupId, (int) $data['shopping_list_id']);

            if (! $list) {
                throw new FamilyGroupException('SHOPPING_LIST_NOT_FOUND', 'La lista de compras no existe.', 404);
            }

            if ($list->status === 'completed') {
                throw new FamilyGroupException('SHOPPING_LIST_CLOSED', 'La lista de compras esta cerrada.', 409);
            }

            return $list;
        }

        return $this->lists->create([
            'family_group_id' => $groupId,
            'meal_plan_id'    => null,
            'created_by'      => $user->id,
            'source_type'     => 'recipe',
            'status'          => 'draft',
        ]);
    }

    private function sumConvertedStock(array $unitQtyMap, int $targetUnitId, int $ingredientId): array
    {
        $total = 0.0;
        $incomplete = false;

        foreach ($unitQtyMap as $unitId => $qty) {
            $factor = $this->recipeAvailability->findConversionFactor((int) $unitId, $targetUnitId, $ingredientId);
            if ($factor === null) {
                $incomplete = true;
                continue;
            }
            $total += $qty * $factor;
        }

        return [$total, $incomplete];
    }

    private function assertValidSupermarketSelection(?int $branchId, ?int $chainId): void
    {
        if ($branchId !== null) {
            $exists = SupermarketBranch::where('id', $branchId)->where('status', 'active')->whereNull('deleted_at')->exists();
            if (! $exists) {
                throw new FamilyGroupException('SUPERMARKET_BRANCH_NOT_FOUND', 'La sucursal no existe o no esta activa.', 422);
            }
        }

        if ($chainId !== null) {
            $exists = SupermarketChain::where('id', $chainId)->where('status', 'active')->whereNull('deleted_at')->exists();
            if (! $exists) {
                throw new FamilyGroupException('CHAIN_NOT_FOUND', 'La cadena no existe o no esta activa.', 422);
            }
        }
    }

    /**
     * Picks a purchasable product for a generic ingredient that has no specific_product_id,
     * by reusing the existing products.ingredient_id link (there is no dedicated
     * "default product per ingredient" table). Returns null if no active product exists.
     */
    private function resolveDefaultProduct(int $ingredientId): ?int
    {
        return Product::where('ingredient_id', $ingredientId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Converts a missing quantity (in the recipe's unit) into a whole number of purchasable
     * packages, using the product's net_quantity/package_unit_id. Returns packages = null
     * (no price computed) whenever the product's packaging isn't known or the recipe's unit
     * can't be converted into it — guessing would risk a wildly wrong price (e.g. treating
     * grams as if they were kilogram-priced packages).
     *
     * @return array{0: int|null, 1: int, 2: string|null} [packages, purchaseUnitId, warning]
     */
    private function resolvePackaging(int $productId, float $missingQty, int $unitId, int $ingredientId): array
    {
        $product = Product::find($productId);

        if (! $product || ! $product->net_quantity || ! $product->package_unit_id) {
            return [null, $unitId, $product ? "El producto '{$product->name}' no tiene presentacion (net_quantity/package_unit_id) cargada; no se pudo estimar precio." : null];
        }

        $packageUnitId = (int) $product->package_unit_id;
        $factor = $unitId === $packageUnitId ? 1.0 : $this->recipeAvailability->findConversionFactor($unitId, $packageUnitId, $ingredientId);

        if ($factor === null) {
            return [null, $unitId, "No se pudo calcular cuantos paquetes de '{$product->name}' hacen falta (unidad no convertible)."];
        }

        $neededInPackageUnit = $missingQty * $factor;
        $packages = (int) ceil($neededInPackageUnit / (float) $product->net_quantity);

        return [max(1, $packages), $packageUnitId, null];
    }
}
