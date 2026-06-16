<?php

namespace App\Repositories\PriceRefreshRequests;

use App\Exceptions\Ingredients\IngredientException;
use App\PriceRefreshRequest;
use App\Product;
use App\ScrapingSource;

class PriceRefreshRequestRepository
{
    public function findActiveProductOrFail(int $id): Product
    {
        $product = Product::where('id', $id)
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $product) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'Producto no encontrado.', 404);
        }

        return $product;
    }

    public function hasPendingForUserAndProduct(int $userId, int $productId): bool
    {
        return PriceRefreshRequest::where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('status', 'pending')
            ->exists();
    }

    public function create(array $data): PriceRefreshRequest
    {
        return PriceRefreshRequest::create($data);
    }

    public function update(PriceRefreshRequest $request, array $data): PriceRefreshRequest
    {
        $request->fill($data);
        $request->save();

        return $request->fresh(['user', 'product']);
    }

    public function findOrFail(int $id): PriceRefreshRequest
    {
        $request = PriceRefreshRequest::with(['user', 'product'])->find($id);

        if (! $request) {
            throw new IngredientException('PRICE_REFRESH_REQUEST_NOT_FOUND', 'Solicitud de actualizacion de precio no encontrada.', 404);
        }

        return $request;
    }

    public function paginateForUser(int $userId, array $filters)
    {
        $query = PriceRefreshRequest::with('product')
            ->where('user_id', $userId);

        $this->applyFilters($query, $filters);

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderByDesc('requested_at')->orderByDesc('id')->paginate($perPage);
    }

    public function paginateAdmin(array $filters)
    {
        $query = PriceRefreshRequest::with(['user', 'product']);

        $this->applyFilters($query, $filters);

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderByDesc('requested_at')->orderByDesc('id')->paginate($perPage);
    }

    public function firstActiveSource(): ?ScrapingSource
    {
        return ScrapingSource::where('is_active', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
    }

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('requested_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('requested_at', '<=', $filters['date_to']);
        }
    }
}
