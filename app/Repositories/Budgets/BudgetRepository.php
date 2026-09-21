<?php

namespace App\Repositories\Budgets;

use App\Budget;
use App\BudgetMovement;
use App\Purchase;
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

    public function usage(Budget $budget): array
    {
        $purchases = Purchase::where('family_group_id', $budget->family_group_id)
            ->whereYear('purchase_date', $budget->year)
            ->whereMonth('purchase_date', $budget->month)
            ->whereIn('status', ['confirmed', 'stock_added'])
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(actual_total), 0) as spent, COUNT(*) as count')
            ->first();

        // Purchase movements mirror purchases; only signed manual adjustments affect this total.
        $adjustments = BudgetMovement::where('budget_id', $budget->id)
            ->where('movement_type', 'adjustment')
            ->sum('amount');

        return [
            'spent' => round((float) $purchases->spent - (float) $adjustments, 2),
            'purchase_count' => (int) $purchases->count,
        ];
    }
}
