<?php

namespace App\Services\Budgets;

use App\AuditLog;
use App\Exceptions\Budgets\BudgetException;
use App\Repositories\Budgets\BudgetAlertRepository;
use App\Repositories\Budgets\BudgetRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BudgetAlertService
{
    private $repo;
    private $budgetRepo;
    private $groupRepo;

    public function __construct(
        BudgetAlertRepository $repo,
        BudgetRepository $budgetRepo,
        FamilyGroupRepository $groupRepo
    ) {
        $this->repo       = $repo;
        $this->budgetRepo = $budgetRepo;
        $this->groupRepo  = $groupRepo;
    }

    public function list(int $groupId, int $budgetId, int $userId, array $filters): LengthAwarePaginator
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->budgetRepo->findForGroup($groupId, $budgetId);

        $this->validateFilters($filters);

        return $this->repo->paginateForBudget($budget->id, $filters);
    }

    public function markAsRead(int $groupId, int $budgetId, int $alertId, int $userId, string $ip, string $ua): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->budgetRepo->findForGroup($groupId, $budgetId);
        $alert  = $this->repo->findForBudget($budget->id, $alertId);

        if ($alert->status === 'read') {
            throw new BudgetException('BUDGET_ALERT_ALREADY_READ', 'La alerta ya fue marcada como leida.', 409);
        }

        return DB::transaction(function () use ($alert, $userId, $ip, $ua) {
            $old     = ['status' => $alert->status, 'read_at' => $alert->read_at];
            $updated = $this->repo->markAsRead($alert);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'budget_alert.read',
                'entity_name' => 'budget_alerts',
                'entity_id'   => (string) $updated->id,
                'old_values'  => $old,
                'new_values'  => ['status' => 'read', 'read_at' => (string) $updated->read_at],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $this->formatAlert($updated);
        });
    }

    private function validateFilters(array $filters): void
    {
        if (!empty($filters['alert_type']) && !in_array($filters['alert_type'], BudgetAlertRepository::VALID_TYPES)) {
            throw new BudgetException('BUDGET_ALERT_TYPE_INVALID', 'Tipo de alerta no valido.', 422);
        }

        if (!empty($filters['status']) && !in_array($filters['status'], BudgetAlertRepository::VALID_STATUSES)) {
            throw new BudgetException('BUDGET_ALERT_STATUS_INVALID', 'Estado de alerta no valido.', 422);
        }

        if (!empty($filters['severity']) && !in_array($filters['severity'], BudgetAlertRepository::VALID_SEVERITIES)) {
            throw new BudgetException('BUDGET_ALERT_SEVERITY_INVALID', 'Severidad de alerta no valida.', 422);
        }
    }

    private function formatAlert($alert): array
    {
        return [
            'id'         => $alert->id,
            'budget_id'  => $alert->budget_id,
            'alert_type' => $alert->alert_type,
            'message'    => $alert->message,
            'severity'   => $alert->severity,
            'status'     => $alert->status,
            'read_at'    => $alert->read_at,
            'created_at' => $alert->created_at,
        ];
    }
}
