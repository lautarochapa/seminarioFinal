<?php

namespace App\Services\RecipeShoppingList;

use App\Repositories\Purchases\PurchaseItemRepository;
use App\Repositories\SupermarketProducts\SupermarketProductRepository;

/**
 * Resolves an estimated unit price for a product using, in order:
 * 1. current price at the selected branch,
 * 2. best current price within the selected chain,
 * 3. last price the family group actually paid for the product,
 * 4. best current price available anywhere,
 * 5. null when none of the above exist.
 */
class RecipePriceEstimator
{
    private $supermarketProducts;
    private $purchaseItems;

    public function __construct(SupermarketProductRepository $supermarketProducts, PurchaseItemRepository $purchaseItems)
    {
        $this->supermarketProducts = $supermarketProducts;
        $this->purchaseItems = $purchaseItems;
    }

    public function resolve(int $productId, int $groupId, ?int $branchId, ?int $chainId): array
    {
        if ($branchId) {
            $price = $this->supermarketProducts->currentPriceAtBranch($productId, $branchId);
            if ($price) {
                return $this->result((float) $price->price, 'branch', (string) $price->scraped_at, $branchId, null);
            }
        }

        if ($chainId) {
            $price = $this->supermarketProducts->bestPriceInChain($productId, $chainId);
            if ($price) {
                return $this->result((float) $price->price, 'chain', (string) $price->scraped_at, null, $chainId);
            }
        }

        $lastPaid = $this->purchaseItems->lastPriceForProductInGroup($productId, $groupId);
        if ($lastPaid !== null) {
            return $this->result($lastPaid['price'], 'group_history', $lastPaid['updated_at'], null, null);
        }

        $best = $this->supermarketProducts->bestPriceForProduct($productId);
        if ($best && $best->current_price) {
            return $this->result(
                (float) $best->current_price->price,
                'best_available',
                (string) $best->current_price->scraped_at,
                $best->supermarket_branch_id,
                $best->supermarket_chain_id
            );
        }

        return $this->result(null, null, null, null, null);
    }

    private function result(?float $price, ?string $source, ?string $updatedAt, ?int $branchId, ?int $chainId): array
    {
        return [
            'unit_price' => $price,
            'source'     => $source,
            'updated_at' => $updatedAt,
            'branch_id'  => $branchId,
            'chain_id'   => $chainId,
        ];
    }
}
