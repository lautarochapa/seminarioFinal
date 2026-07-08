<?php

namespace App\Repositories\ProductRequests;

use App\Exceptions\Ingredients\IngredientException;
use App\ProductRequest;

class ProductRequestRepository
{
    public function paginate(array $filters, $actor)
    {
        $query = ProductRequest::with(['requester', 'familyGroup', 'unit', 'product']);

        if (! $actor->hasPermission('catalog.manage')) {
            $query->where('requested_by_user_id', $actor->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('brand', 'ILIKE', $search)
                    ->orWhere('presentation', 'ILIKE', $search)
                    ->orWhere('barcode', 'ILIKE', $search);
            });
        }

        $sortMap = [
            'id' => 'id',
            'name' => 'name',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'created_at'] ?? 'created_at';
        $order = strtolower($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy($sort, $order)->orderBy('id', 'desc')->paginate($perPage);
    }

    public function findVisibleOrFail($id, $actor)
    {
        $query = ProductRequest::with(['requester', 'familyGroup', 'unit', 'product'])->where('id', $id);

        if (! $actor->hasPermission('catalog.manage')) {
            $query->where('requested_by_user_id', $actor->id);
        }

        $request = $query->first();
        if (! $request) {
            throw new IngredientException('PRODUCT_REQUEST_NOT_FOUND', 'La solicitud de producto no existe.', 404);
        }

        return $request;
    }

    public function findForReviewOrFail($id)
    {
        $request = ProductRequest::with(['requester', 'familyGroup', 'unit', 'product'])->where('id', $id)->lockForUpdate()->first();
        if (! $request) {
            throw new IngredientException('PRODUCT_REQUEST_NOT_FOUND', 'La solicitud de producto no existe.', 404);
        }

        return $request;
    }

    public function create(array $data)
    {
        return ProductRequest::create($data)->fresh(['requester', 'familyGroup', 'unit', 'product']);
    }

    public function update(ProductRequest $request, array $data)
    {
        $request->fill($data);
        $request->save();

        return $request->fresh(['requester', 'familyGroup', 'unit', 'product']);
    }

    public function pendingDuplicateExists($userId, $normalizedName, $barcode = null)
    {
        return ProductRequest::where('requested_by_user_id', $userId)
            ->where('status', ProductRequest::STATUS_PENDING)
            ->where(function ($query) use ($normalizedName, $barcode) {
                $query->where('normalized_name', $normalizedName);
                if ($barcode) {
                    $query->orWhere('barcode', $barcode);
                }
            })
            ->exists();
    }
}
