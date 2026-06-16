<?php

namespace App\Repositories\StockMovements;

use App\StockMovement;

class StockMovementRepository
{
    public function paginateForGroup(int $groupId, array $filters)
    {
        $query = StockMovement::with(['product', 'stockItem.location', 'unit', 'creator'])
            ->where('family_group_id', $groupId);

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        if (! empty($filters['type'])) {
            $query->where('movement_type', $filters['type']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('created_by', (int) $filters['user_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }

    public function create(array $data): StockMovement
    {
        return StockMovement::create($data);
    }
}
