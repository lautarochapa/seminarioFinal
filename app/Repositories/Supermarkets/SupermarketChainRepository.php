<?php

namespace App\Repositories\Supermarkets;

use App\Exceptions\Ingredients\IngredientException;
use App\SupermarketChain;

class SupermarketChainRepository
{
    public function paginate(array $filters)
    {
        $query = SupermarketChain::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search);
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy('name', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $chain = SupermarketChain::find($id);

        if (! $chain) {
            throw new IngredientException('CHAIN_NOT_FOUND', 'La cadena solicitada no existe.', 404);
        }

        return $chain;
    }

    public function findWithTrashedOrFail($id)
    {
        $chain = SupermarketChain::withTrashed()->find($id);

        if (! $chain) {
            throw new IngredientException('CHAIN_NOT_FOUND', 'La cadena solicitada no existe.', 404);
        }

        return $chain;
    }

    public function nameExists(string $name, $exceptId = null)
    {
        $query = SupermarketChain::withTrashed()->where('name', $name);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function codeExists(string $code)
    {
        return SupermarketChain::withTrashed()->where('code', $code)->exists();
    }

    public function create(array $data)
    {
        return SupermarketChain::create($data);
    }

    public function update(SupermarketChain $chain, array $data)
    {
        $chain->fill($data);
        $chain->save();

        return $chain->fresh();
    }

    public function softDelete(SupermarketChain $chain)
    {
        $chain->status = 'inactive';
        $chain->save();
        $chain->delete();

        return SupermarketChain::withTrashed()->find($chain->id);
    }

    public function restore(SupermarketChain $chain)
    {
        $chain->restore();
        $chain->status = 'active';
        $chain->save();

        return $chain->fresh();
    }

    public function allActive()
    {
        return SupermarketChain::where('status', 'active')
            ->orderBy('name', 'asc')
            ->get();
    }

    public function findActiveOrFail($id)
    {
        $chain = SupermarketChain::where('id', $id)->where('status', 'active')->first();

        if (! $chain) {
            throw new IngredientException('CHAIN_NOT_FOUND', 'La cadena solicitada no existe.', 404);
        }

        return $chain;
    }
}
