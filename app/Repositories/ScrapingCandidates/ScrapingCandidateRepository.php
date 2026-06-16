<?php

namespace App\Repositories\ScrapingCandidates;

use App\ScrapedProductCandidate;
use App\SupermarketProduct;
use App\SupermarketProductPrice;
use App\Exceptions\Ingredients\IngredientException;

class ScrapingCandidateRepository
{
    public function paginate(array $filters)
    {
        $query = ScrapedProductCandidate::with(['source', 'job', 'suggestedProduct', 'suggestedIngredient', 'reviewer']);

        if (!empty($filters['review_status'])) {
            $query->where('review_status', $filters['review_status']);
        }
        if (!empty($filters['source_id'])) {
            $query->where('source_id', (int) $filters['source_id']);
        }
        if (!empty($filters['scraping_job_id'])) {
            $query->where('scraping_job_id', (int) $filters['scraping_job_id']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('raw_name', 'like', '%' . $s . '%')
                  ->orWhere('external_product_id', 'like', '%' . $s . '%')
                  ->orWhere('raw_product_url', 'like', '%' . $s . '%');
            });
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function findOrFail(int $id): ScrapedProductCandidate
    {
        $candidate = ScrapedProductCandidate::with([
            'source', 'job', 'suggestedProduct', 'suggestedIngredient', 'reviewer',
        ])->find($id);

        if (!$candidate) {
            throw new IngredientException(
                'CANDIDATE_NOT_FOUND',
                'Candidato de producto no encontrado.',
                404
            );
        }

        return $candidate;
    }

    public function update(ScrapedProductCandidate $candidate, array $data): ScrapedProductCandidate
    {
        $candidate->fill($data);
        $candidate->save();
        return $candidate;
    }

    public function findSupermarketProductMapping(int $chainId, int $branchId, int $productId): ?SupermarketProduct
    {
        return SupermarketProduct::where('supermarket_chain_id', $chainId)
            ->where('supermarket_branch_id', $branchId)
            ->where('product_id', $productId)
            ->first();
    }

    public function createSupermarketProduct(array $data): SupermarketProduct
    {
        return SupermarketProduct::create($data);
    }

    public function currentActivePrice(int $supermarketProductId): ?SupermarketProductPrice
    {
        return SupermarketProductPrice::where('supermarket_product_id', $supermarketProductId)
            ->whereNull('valid_to')
            ->where('status', 'active')
            ->latest('created_at')
            ->first();
    }

    public function createPrice(int $supermarketProductId, array $data): SupermarketProductPrice
    {
        return SupermarketProductPrice::create(array_merge(
            ['supermarket_product_id' => $supermarketProductId],
            $data
        ));
    }
}
