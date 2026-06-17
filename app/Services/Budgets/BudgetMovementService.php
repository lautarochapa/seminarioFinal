<?php

namespace App\Services\Budgets;

use App\AuditLog;
use App\Exceptions\Budgets\BudgetException;
use App\Repositories\Budgets\BudgetMovementRepository;
use App\Repositories\Budgets\BudgetRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BudgetMovementService
{
    private $repo;
    private $budgetRepo;
    private $groupRepo;

    public function __construct(
        BudgetMovementRepository $repo,
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

        if (!empty($filters['movement_type']) && !in_array($filters['movement_type'], BudgetMovementRepository::VALID_TYPES)) {
            throw new BudgetException('BUDGET_MOVEMENT_TYPE_INVALID', 'Tipo de movimiento no valido.', 422);
        }

        return $this->repo->paginateForBudget($budget->id, $filters);
    }

    public function createAdjustment(int $groupId, int $budgetId, int $userId, array $data, string $ip, string $ua): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->budgetRepo->findForGroup($groupId, $budgetId);

        $amount = (float) $data['amount'];

        if ($amount == 0) {
            throw new BudgetException('BUDGET_ADJUSTMENT_ZERO', 'El monto del ajuste no puede ser cero.', 422);
        }

        return DB::transaction(function () use ($budget, $userId, $data, $amount, $ip, $ua) {
            $movement = $this->repo->create([
                'budget_id'     => $budget->id,
                'movement_type' => 'adjustment',
                'amount'        => $amount,
                'description'   => $data['description'],
                'created_at'    => now(),
            ]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'budget_movement.adjustment',
                'entity_name' => 'budget_movements',
                'entity_id'   => (string) $movement->id,
                'old_values'  => null,
                'new_values'  => [
                    'budget_id'     => $budget->id,
                    'movement_type' => 'adjustment',
                    'amount'        => $amount,
                    'description'   => $data['description'],
                ],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $this->formatMovement($movement);
        });
    }

    private function formatMovement($movement): array
    {
        return [
            'id'                       => $movement->id,
            'budget_id'                => $movement->budget_id,
            'movement_type'            => $movement->movement_type,
            'amount'                   => (float) $movement->amount,
            'description'              => $movement->description,
            'related_purchase_id'      => $movement->related_purchase_id,
            'related_shopping_list_id' => $movement->related_shopping_list_id,
            'created_at'               => $movement->created_at,
        ];
    }
}
