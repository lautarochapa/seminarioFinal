<?php

namespace App\Repositories\SupermarketComparison;

use App\SupermarketBranch;
use App\SupermarketProductPrice;
use Illuminate\Support\Collection;

class SupermarketComparisonRepository
{
    public function activeBranches(): Collection
    {
        return SupermarketBranch::with('chain')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereHas('chain', function ($query) {
                $query->where('status', 'active')->whereNull('deleted_at');
            })
            ->orderBy('name')
            ->get();
    }

    public function latestPriceForBranch(int $branchId, int $productId, int $unitId): ?SupermarketProductPrice
    {
        return SupermarketProductPrice::with(['promotion', 'supermarketProduct'])
            ->where('status', 'active')
            ->whereHas('supermarketProduct', function ($query) use ($branchId, $productId, $unitId) {
                $query->where('supermarket_branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->where('status', 'active')
                    ->whereHas('product', function ($product) use ($unitId) {
                        $product->where('status', 'active')
                            ->where('is_active', true)
                            ->where('default_unit_id', $unitId)
                            ->whereNull('deleted_at');
                    });
            })
            ->orderByDesc('scraped_at')
            ->orderByDesc('id')
            ->first();
    }
}
