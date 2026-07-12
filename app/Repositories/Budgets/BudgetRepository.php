<?php

namespace App\Repositories\Budgets;

use App\Budget;
use App\Exceptions\Budgets\BudgetException;

class BudgetRepository
{
    public function paginateForGroup(int $groupId, array $filters)
    {
        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        return Budget::where('family_group_id', $groupId)
            ->whereNull('deleted_at')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($perPage);
    }

    public function findForGroup(int $groupId, int $budgetId): Budget
    {
        $budget = Budget::where('family_group_id', $groupId)
            ->where('id', $budgetId)
            ->whereNull('deleted_at')
            ->first();

        if (!$budget) {
            throw new BudgetException('BUDGET_NOT_FOUND', 'El presupuesto no existe o no pertenece al grupo.', 404);
        }

        return $budget;
    }

    public function findCurrentForGroup(int $groupId): ?Budget
    {
        return Budget::where('family_group_id', $groupId)
            ->where('year', (int) now()->format('Y'))
            ->where('month', (int) now()->format('n'))
            ->whereNull('deleted_at')
            ->first();
    }

    public function existsForPeriod(int $groupId, int $year, int $month): bool
    {
        return Budget::where('family_group_id', $groupId)
            ->where('year', $year)
            ->where('month', $month)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function create(array $data): Budget
    {
        return Budget::create($data);
    }

    public function update(Budget $budget, array $data): Budget
    {
        $budget->update($data);
        return $budget->fresh();
    }

    public function delete(Budget $budget): Budget
    {
        $budget->update(['status' => 'inactive']);
        $budget->delete();
        return Budget::withTrashed()->find($budget->id);
    }

    public function usedAmount(int $groupId, int $year, int $month): float
    {
        return (float) \App\Purchase::where('family_group_id', $groupId)
            ->whereYear('purchase_date', $year)
            ->whereMonth('purchase_date', $month)
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->sum('actual_total');
    }
}
