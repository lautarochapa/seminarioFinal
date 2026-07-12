<?php

namespace App\Repositories\Budgets;

use App\BudgetMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BudgetMovementRepository
{
    const VALID_TYPES = ['purchase', 'planned_purchase', 'adjustment', 'reservation', 'release'];

    public function paginateForBudget(int $budgetId, array $filters): LengthAwarePaginator
    {
        $query = BudgetMovement::where('budget_id', $budgetId)
            ->with(['purchase', 'shoppingList'])
            ->orderByDesc('created_at');

        if (!empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $perPage = max($perPage, 1);

        return $query->paginate($perPage);
    }

    public function create(array $data): BudgetMovement
    {
        return BudgetMovement::create($data);
    }
}
