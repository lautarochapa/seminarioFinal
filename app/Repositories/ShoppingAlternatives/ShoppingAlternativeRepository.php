<?php

namespace App\Repositories\ShoppingAlternatives;

use App\IngredientEquivalence;
use App\ShoppingListItemAlternative;
use App\SupermarketProduct;
use Illuminate\Support\Collection;

class ShoppingAlternativeRepository
{
    public function compatibleIngredientIds(int $ingredientId): array
    {
        $ids = [$ingredientId];

        $equivalences = IngredientEquivalence::where('status', 'active')
            ->where(function ($query) use ($ingredientId) {
                $query->where('source_ingredient_id', $ingredientId)
                    ->orWhere('target_ingredient_id', $ingredientId);
            })
            ->get(['source_ingredient_id', 'target_ingredient_id']);

        foreach ($equivalences as $equivalence) {
            $ids[] = (int) $equivalence->source_ingredient_id;
            $ids[] = (int) $equivalence->target_ingredient_id;
        }

        return array_values(array_unique($ids));
    }

    public function productsForIngredients(array $ingredientIds, int $unitId, ?int $currentProductId = null, bool $packageCount = false): Collection
    {
        $query = SupermarketProduct::with([
                'product',
                'prices' => function ($prices) {
                    $prices->where('status', 'active')
                        ->where(function ($query) { $query->whereNull('valid_from')->orWhere('valid_from', '<=', now()); })
                        ->where(function ($query) { $query->whereNull('valid_to')->orWhere('valid_to', '>=', now()); })
                        ->orderByDesc('scraped_at')
                        ->orderByDesc('id');
                },
            ])
            ->where('status', 'active')
            ->whereHas('product', function ($product) use ($ingredientIds, $unitId, $currentProductId, $packageCount) {
                $product->whereIn('ingredient_id', $ingredientIds)
                    ->when(!$packageCount, function ($query) use ($unitId) {
                        $query->where(function ($units) use ($unitId) {
                            $units->where('default_unit_id', $unitId)
                                ->orWhere(function ($packaged) {
                                    $packaged->where('net_quantity', '>', 0)->whereNotNull('package_unit_id');
                                });
                        });
                    })
                    ->where('status', 'active')
                    ->where('is_active', true)
                    ->whereNull('deleted_at');

                if ($currentProductId !== null) {
                    $product->where('id', '<>', $currentProductId);
                }
            })
            ->orderBy('id');

        return $query->get();
    }

    public function findAlternativeForItem(int $itemId, int $alternativeId): ?ShoppingListItemAlternative
    {
        return ShoppingListItemAlternative::with(['product', 'supermarketProduct'])
            ->where('shopping_list_item_id', $itemId)
            ->where('id', $alternativeId)
            ->first();
    }

    public function selectionForOffer(int $itemId, int $offerId): ShoppingListItemAlternative
    {
        return ShoppingListItemAlternative::firstOrNew([
            'shopping_list_item_id' => $itemId,
            'supermarket_product_id' => $offerId,
        ]);
    }

    public function clearSelected(int $itemId): void
    {
        ShoppingListItemAlternative::where('shopping_list_item_id', $itemId)->update(['is_selected' => false]);
    }
}
