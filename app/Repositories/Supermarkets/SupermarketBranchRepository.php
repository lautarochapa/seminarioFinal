<?php

namespace App\Repositories\Supermarkets;

use App\Exceptions\Ingredients\IngredientException;
use App\SupermarketBranch;

class SupermarketBranchRepository
{
    public function paginate(array $filters)
    {
        $query = SupermarketBranch::with(['chain', 'city']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['city_id'])) {
            $query->where('city_id', (int) $filters['city_id']);
        }

        if (! empty($filters['chain_id'])) {
            $query->where('supermarket_chain_id', (int) $filters['chain_id']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('address', 'ILIKE', $search);
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy('name', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $branch = SupermarketBranch::with(['chain', 'city'])->find($id);

        if (! $branch) {
            throw new IngredientException('BRANCH_NOT_FOUND', 'La sucursal solicitada no existe.', 404);
        }

        return $branch;
    }

    public function findWithTrashedOrFail($id)
    {
        $branch = SupermarketBranch::withTrashed()->with(['chain', 'city'])->find($id);

        if (! $branch) {
            throw new IngredientException('BRANCH_NOT_FOUND', 'La sucursal solicitada no existe.', 404);
        }

        return $branch;
    }

    public function create(array $data)
    {
        $branch = SupermarketBranch::create($data);

        return $branch->load(['chain', 'city']);
    }

    public function update(SupermarketBranch $branch, array $data)
    {
        $branch->fill($data);
        $branch->save();

        return $branch->fresh(['chain', 'city']);
    }

    public function softDelete(SupermarketBranch $branch)
    {
        $branch->status = 'inactive';
        $branch->save();
        $branch->delete();

        return SupermarketBranch::withTrashed()->with(['chain', 'city'])->find($branch->id);
    }

    public function restore(SupermarketBranch $branch)
    {
        $branch->restore();
        $branch->status = 'active';
        $branch->save();

        return $branch->fresh(['chain', 'city']);
    }

    public function allActive(array $filters = [])
    {
        $query = SupermarketBranch::with(['chain', 'city'])
            ->where('status', 'active');

        if (! empty($filters['city_id'])) {
            $query->where('city_id', (int) $filters['city_id']);
        }

        if (! empty($filters['chain_id'])) {
            $query->where('supermarket_chain_id', (int) $filters['chain_id']);
        }

        return $query->orderBy('name', 'asc')->get();
    }

    public function allActiveWithCoordinates()
    {
        return SupermarketBranch::with(['chain', 'city'])
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
    }

    public function findActiveOrFail($id)
    {
        $branch = SupermarketBranch::with(['chain', 'city'])
            ->where('id', $id)
            ->where('status', 'active')
            ->first();

        if (! $branch) {
            throw new IngredientException('BRANCH_NOT_FOUND', 'La sucursal solicitada no existe.', 404);
        }

        return $branch;
    }
}
