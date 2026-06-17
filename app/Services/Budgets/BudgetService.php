<?php

namespace App\Services\Budgets;

use App\AuditLog;
use App\Budget;
use App\Exceptions\Budgets\BudgetException;
use App\Repositories\Budgets\BudgetRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    const VALID_CURRENCIES = ['ARS', 'USD', 'EUR'];

    private $repo;
    private $groupRepo;

    public function __construct(BudgetRepository $repo, FamilyGroupRepository $groupRepo)
    {
        $this->repo      = $repo;
        $this->groupRepo = $groupRepo;
    }

    public function list(int $groupId, int $userId, array $filters)
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        return $this->repo->paginateForGroup($groupId, $filters);
    }

    public function current(int $groupId, int $userId): ?Budget
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        return $this->repo->findCurrentForGroup($groupId);
    }

    public function create(int $groupId, int $userId, array $data, string $ip, string $ua): Budget
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $year  = (int) $data['year'];
        $month = (int) $data['month'];

        if ($this->repo->existsForPeriod($groupId, $year, $month)) {
            throw new BudgetException('BUDGET_PERIOD_ALREADY_EXISTS', 'Ya existe un presupuesto para ese mes y año.', 409);
        }

        return DB::transaction(function () use ($groupId, $userId, $data, $ip, $ua, $year, $month) {
            $budget = $this->repo->create([
                'family_group_id' => $groupId,
                'year'            => $year,
                'month'           => $month,
                'total_amount'    => $data['total_amount'],
                'currency'        => $data['currency'] ?? 'ARS',
                'status'          => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'budget.create',
                'entity_name' => 'budgets',
                'entity_id'   => (string) $budget->id,
                'old_values'  => null,
                'new_values'  => ['year' => $year, 'month' => $month, 'total_amount' => $budget->total_amount, 'currency' => $budget->currency],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $budget;
        });
    }

    public function update(int $groupId, int $budgetId, int $userId, array $data, string $ip, string $ua): Budget
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->repo->findForGroup($groupId, $budgetId);

        if ($budget->status !== 'active') {
            throw new BudgetException('BUDGET_NOT_EDITABLE', 'El presupuesto no puede modificarse en su estado actual.', 409);
        }

        return DB::transaction(function () use ($budget, $userId, $data, $ip, $ua) {
            $allowed  = ['total_amount', 'currency'];
            $filtered = array_filter(
                array_intersect_key($data, array_flip($allowed)),
                function ($v) { return $v !== null; }
            );
            $old     = $budget->only(array_keys($filtered));
            $updated = $this->repo->update($budget, $filtered);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'budget.update',
                'entity_name' => 'budgets',
                'entity_id'   => (string) $budget->id,
                'old_values'  => $old,
                'new_values'  => $filtered,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $updated;
        });
    }

    public function delete(int $groupId, int $budgetId, int $userId, string $ip, string $ua): Budget
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->repo->findForGroup($groupId, $budgetId);

        return DB::transaction(function () use ($budget, $userId, $ip, $ua) {
            $deleted = $this->repo->delete($budget);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'budget.delete',
                'entity_name' => 'budgets',
                'entity_id'   => (string) $budget->id,
                'old_values'  => ['status' => 'active', 'deleted_at' => null],
                'new_values'  => ['status' => 'inactive', 'deleted_at' => (string) now()],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $deleted;
        });
    }

    public function withUsage(Budget $budget): array
    {
        $used      = $this->repo->usedAmount($budget->family_group_id, $budget->year, $budget->month);
        $total     = (float) $budget->total_amount;
        $available = max(0, $total - $used);
        $percent   = $total > 0 ? round(($used / $total) * 100, 2) : 0;

        return [
            'budget'    => $budget,
            'used'      => round($used, 2),
            'available' => round($available, 2),
            'percent'   => $percent,
        ];
    }
}
