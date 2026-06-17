<?php

namespace App\Repositories\Budgets;

use App\BudgetAlert;
use App\Exceptions\Budgets\BudgetException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BudgetAlertRepository
{
    const VALID_TYPES      = ['near_limit', 'limit_exceeded', 'projected_near_limit', 'projected_exceeded'];
    const VALID_SEVERITIES = ['info', 'warning', 'critical'];
    const VALID_STATUSES   = ['unread', 'read'];

    public function paginateForBudget(int $budgetId, array $filters): LengthAwarePaginator
    {
        $query = BudgetAlert::where('budget_id', $budgetId)
            ->orderByDesc('created_at');

        if (!empty($filters['alert_type'])) {
            $query->where('alert_type', $filters['alert_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        $perPage = max($perPage, 1);

        return $query->paginate($perPage);
    }

    public function findForBudget(int $budgetId, int $alertId): BudgetAlert
    {
        $alert = BudgetAlert::where('budget_id', $budgetId)
            ->where('id', $alertId)
            ->first();

        if (!$alert) {
            throw new BudgetException('BUDGET_ALERT_NOT_FOUND', 'La alerta no existe o no pertenece a este presupuesto.', 404);
        }

        return $alert;
    }

    public function existsUnreadForType(int $budgetId, string $alertType): bool
    {
        return BudgetAlert::where('budget_id', $budgetId)
            ->where('alert_type', $alertType)
            ->where('status', 'unread')
            ->exists();
    }

    public function markAsRead(BudgetAlert $alert): BudgetAlert
    {
        $alert->update(['status' => 'read', 'read_at' => now()]);
        return $alert->fresh();
    }

    public function create(array $data): BudgetAlert
    {
        return BudgetAlert::create($data);
    }
}
