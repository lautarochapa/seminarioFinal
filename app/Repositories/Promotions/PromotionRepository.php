<?php

namespace App\Repositories\Promotions;

use App\Exceptions\Ingredients\IngredientException;
use App\Promotion;
use App\SupermarketBranch;

class PromotionRepository
{
    public function paginate(array $filters)
    {
        $query = Promotion::with(['chain', 'branch']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['chain_id'])) {
            $query->where('supermarket_chain_id', (int) $filters['chain_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('supermarket_branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where('name', 'ILIKE', $search);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $promotion = Promotion::with(['chain', 'branch'])->find($id);

        if (! $promotion) {
            throw new IngredientException('PROMOTION_NOT_FOUND', 'La promocion no fue encontrada.', 404);
        }

        return $promotion;
    }

    public function findWithTrashedOrFail($id)
    {
        $promotion = Promotion::withTrashed()->with(['chain', 'branch'])->find($id);

        if (! $promotion) {
            throw new IngredientException('PROMOTION_NOT_FOUND', 'La promocion no fue encontrada.', 404);
        }

        return $promotion;
    }

    public function create(array $data)
    {
        $promotion = Promotion::create($data);
        return $promotion->load(['chain', 'branch']);
    }

    public function update(Promotion $promotion, array $data)
    {
        $promotion->fill($data);
        $promotion->save();
        return $promotion->fresh(['chain', 'branch']);
    }

    public function softDelete(Promotion $promotion)
    {
        $promotion->status = 'inactive';
        $promotion->save();
        $promotion->delete();
        return Promotion::withTrashed()->with(['chain', 'branch'])->find($promotion->id);
    }

    public function restore(Promotion $promotion)
    {
        $promotion->restore();
        $promotion->status = 'active';
        $promotion->save();
        return $promotion->fresh(['chain', 'branch']);
    }

    public function forBranch(int $branchId, array $filters = [])
    {
        $branch = SupermarketBranch::find($branchId);

        if (! $branch || $branch->status !== 'active') {
            throw new IngredientException('SUPERMARKET_BRANCH_NOT_FOUND', 'Sucursal no encontrada o inactiva.', 404);
        }

        $query = Promotion::with(['chain', 'branch'])
            ->where('status', 'active')
            ->where(function ($q) use ($branch) {
                $q->where('supermarket_branch_id', $branch->id)
                    ->orWhere(function ($q2) use ($branch) {
                        $q2->where('supermarket_chain_id', $branch->supermarket_chain_id)
                            ->whereNull('supermarket_branch_id');
                    });
            })
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            });

        if (isset($filters['day_of_week']) && $filters['day_of_week'] !== '') {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('day_of_week')
                    ->orWhere('day_of_week', (int) $filters['day_of_week']);
            });
        }

        return $query->orderByDesc('valid_from')->orderByDesc('id')->get();
    }
}
