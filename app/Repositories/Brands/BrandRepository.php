<?php

namespace App\Repositories\Brands;

use App\Brand;
use App\Exceptions\Ingredients\IngredientException;

class BrandRepository
{
    public function paginate(array $filters, $publicOnly = false)
    {
        $query = Brand::query();

        if ($publicOnly) {
            $query->where('status', 'active');
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('normalized_name', 'ILIKE', $search)
                    ->orWhere('nombre', 'ILIKE', $search);
            });
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        $sortMap = [
            'id' => 'id',
            'name' => 'name',
            'normalized_name' => 'normalized_name',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'name'] ?? 'name';
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy($sort, $order)->orderBy('id')->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $brand = Brand::find($id);

        if (! $brand) {
            throw new IngredientException('BRAND_NOT_FOUND', 'La marca solicitada no existe.', 404);
        }

        return $brand;
    }

    public function findWithTrashedOrFail($id)
    {
        $brand = Brand::withTrashed()->find($id);

        if (! $brand) {
            throw new IngredientException('BRAND_NOT_FOUND', 'La marca solicitada no existe.', 404);
        }

        return $brand;
    }

    public function activeNameExists($normalizedName, $exceptId = null)
    {
        $query = Brand::where('normalized_name', $normalizedName)
            ->where('status', 'active');

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return Brand::create($data);
    }

    public function update(Brand $brand, array $data)
    {
        $brand->fill($data);
        $brand->save();

        return $brand->fresh();
    }
}
