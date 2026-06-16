<?php

namespace App\Repositories\SupermarketProducts;

use App\Exceptions\Ingredients\IngredientException;
use App\SupermarketProduct;
use App\SupermarketProductPrice;

class SupermarketProductRepository
{
    public function paginate(array $filters)
    {
        $query = SupermarketProduct::with(['product', 'branch', 'branch.chain']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('supermarket_branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['chain_id'])) {
            $query->where('supermarket_chain_id', (int) $filters['chain_id']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('external_sku', 'ILIKE', $search)
                    ->orWhere('source_name', 'ILIKE', $search);
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $sp = SupermarketProduct::with(['product', 'branch', 'branch.chain'])->find($id);

        if (! $sp) {
            throw new IngredientException('SUPERMARKET_PRODUCT_NOT_FOUND', 'El mapeo solicitado no existe.', 404);
        }

        return $sp;
    }

    public function existsDuplicate(int $productId, int $branchId, string $sku, $exceptId = null)
    {
        $query = SupermarketProduct::where('product_id', $productId)
            ->where('supermarket_branch_id', $branchId)
            ->where('external_sku', $sku)
            ->where('status', 'active');

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        $sp = SupermarketProduct::create($data);
        return $sp->load(['product', 'branch', 'branch.chain']);
    }

    public function update(SupermarketProduct $sp, array $data)
    {
        $sp->fill($data);
        $sp->save();
        return $sp->fresh(['product', 'branch', 'branch.chain']);
    }

    public function deactivate(SupermarketProduct $sp)
    {
        $sp->status = 'inactive';
        $sp->save();
        return $sp->fresh(['product', 'branch', 'branch.chain']);
    }

    public function activate(SupermarketProduct $sp)
    {
        $sp->status = 'active';
        $sp->save();
        return $sp->fresh(['product', 'branch', 'branch.chain']);
    }

    public function forBranch(int $branchId, array $filters = [])
    {
        $query = SupermarketProduct::with(['product', 'branch', 'branch.chain', 'branch.city',
            'prices' => function ($q) {
                $q->where('status', 'active')
                    ->orderByDesc('scraped_at')
                    ->orderByDesc('id');
            },
        ])
        ->where('supermarket_branch_id', $branchId)
        ->where('status', 'active');

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('external_sku', 'ILIKE', $search)
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'ILIKE', $search);
                    });
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy('id')->paginate($perPage);
    }

    public function pricesForProduct(int $productId)
    {
        $supermarketProducts = SupermarketProduct::where('product_id', $productId)
            ->where('status', 'active')
            ->with([
                'branch',
                'branch.chain',
                'branch.city',
                'prices' => function ($q) {
                    $q->where('status', 'active')
                        ->orderByDesc('scraped_at')
                        ->orderByDesc('id');
                },
            ])
            ->get();

        return $supermarketProducts
            ->map(function ($sp) {
                $sp->current_price = $sp->prices->first();
                return $sp;
            })
            ->filter(function ($sp) {
                return $sp->current_price !== null;
            })
            ->sortBy(function ($sp) {
                return (float) $sp->current_price->price;
            })
            ->values();
    }

    public function bestPriceForProduct(int $productId)
    {
        $supermarketProducts = SupermarketProduct::where('product_id', $productId)
            ->where('status', 'active')
            ->with([
                'product',
                'branch',
                'branch.chain',
                'branch.city',
                'prices' => function ($q) {
                    $q->where('status', 'active')
                        ->orderByDesc('scraped_at')
                        ->orderByDesc('id');
                },
            ])
            ->get();

        $withPrice = $supermarketProducts->map(function ($sp) {
            $sp->current_price = $sp->prices->first();
            return $sp;
        })->filter(function ($sp) {
            return $sp->current_price !== null;
        });

        if ($withPrice->isEmpty()) {
            return null;
        }

        return $withPrice->sortBy(function ($sp) {
            return (float) $sp->current_price->price;
        })->first();
    }
}
