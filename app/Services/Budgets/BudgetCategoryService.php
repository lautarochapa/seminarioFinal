<?php

namespace App\Services\Budgets;

use App\AuditLog;
use App\Exceptions\Budgets\BudgetException;
use App\Repositories\Budgets\BudgetCategoryRepository;
use App\Repositories\Budgets\BudgetRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use Illuminate\Support\Facades\DB;

class BudgetCategoryService
{
    private $repo;
    private $budgetRepo;
    private $groupRepo;

    public function __construct(
        BudgetCategoryRepository $repo,
        BudgetRepository $budgetRepo,
        FamilyGroupRepository $groupRepo
    ) {
        $this->repo       = $repo;
        $this->budgetRepo = $budgetRepo;
        $this->groupRepo  = $groupRepo;
    }

    public function list(int $groupId, int $budgetId, int $userId)
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->budgetRepo->findForGroup($groupId, $budgetId);

        $cats = $this->repo->listForBudget($budget->id);

        return $cats->map(function ($cat) use ($groupId, $budget) {
            return $this->withSpent($cat, $groupId, $budget);
        });
    }

    public function create(int $groupId, int $budgetId, int $userId, array $data, string $ip, string $ua)
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->budgetRepo->findForGroup($groupId, $budgetId);

        $productCatId    = isset($data['product_category_id']) ? (int) $data['product_category_id'] : null;
        $ingredientCatId = isset($data['ingredient_category_id']) ? (int) $data['ingredient_category_id'] : null;

        $this->validateCategoryChoice($productCatId, $ingredientCatId);

        if ($this->repo->existsDuplicate($budget->id, $productCatId, $ingredientCatId)) {
            throw new BudgetException('BUDGET_CATEGORY_DUPLICATE', 'Ya existe una asignacion para esa categoria en este presupuesto.', 409);
        }

        $amount  = (float) $data['amount'];
        $already = $this->repo->assignedTotal($budget->id);

        if (($already + $amount) > (float) $budget->total_amount) {
            throw new BudgetException('BUDGET_CATEGORY_EXCEEDS_TOTAL', 'La asignacion supera el total del presupuesto.', 422);
        }

        return DB::transaction(function () use ($budget, $userId, $data, $productCatId, $ingredientCatId, $ip, $ua, $groupId) {
            $cat = $this->repo->create([
                'budget_id'              => $budget->id,
                'product_category_id'    => $productCatId,
                'ingredient_category_id' => $ingredientCatId,
                'amount'                 => $data['amount'],
                'status'                 => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'budget_category.create',
                'entity_name' => 'budget_categories',
                'entity_id'   => (string) $cat->id,
                'old_values'  => null,
                'new_values'  => ['budget_id' => $budget->id, 'product_category_id' => $productCatId, 'ingredient_category_id' => $ingredientCatId, 'amount' => $cat->amount],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $this->withSpent($cat->load(['productCategory', 'ingredientCategory']), $groupId, $budget);
        });
    }

    public function update(int $groupId, int $budgetId, int $categoryId, int $userId, array $data, string $ip, string $ua)
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->budgetRepo->findForGroup($groupId, $budgetId);
        $cat    = $this->repo->findForBudget($budget->id, $categoryId);

        $old = ['amount' => $cat->amount];

        if (isset($data['amount'])) {
            $amount  = (float) $data['amount'];
            $already = $this->repo->assignedTotal($budget->id, $cat->id);

            if (($already + $amount) > (float) $budget->total_amount) {
                throw new BudgetException('BUDGET_CATEGORY_EXCEEDS_TOTAL', 'La asignacion supera el total del presupuesto.', 422);
            }
        }

        return DB::transaction(function () use ($cat, $data, $old, $userId, $ip, $ua, $groupId, $budget) {
            $updated = $this->repo->update($cat, ['amount' => $data['amount']]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'budget_category.update',
                'entity_name' => 'budget_categories',
                'entity_id'   => (string) $cat->id,
                'old_values'  => $old,
                'new_values'  => ['amount' => $data['amount']],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $this->withSpent($updated, $groupId, $budget);
        });
    }

    public function delete(int $groupId, int $budgetId, int $categoryId, int $userId, string $ip, string $ua): void
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->budgetRepo->findForGroup($groupId, $budgetId);
        $cat    = $this->repo->findForBudget($budget->id, $categoryId);

        DB::transaction(function () use ($cat, $userId, $ip, $ua) {
            $old = ['amount' => $cat->amount, 'status' => $cat->status];
            $this->repo->deactivate($cat);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'budget_category.delete',
                'entity_name' => 'budget_categories',
                'entity_id'   => (string) $cat->id,
                'old_values'  => $old,
                'new_values'  => ['status' => 'inactive'],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);
        });
    }

    private function validateCategoryChoice(?int $productCatId, ?int $ingredientCatId): void
    {
        if ($productCatId === null && $ingredientCatId === null) {
            throw new BudgetException('BUDGET_CATEGORY_TYPE_REQUIRED', 'Debe indicar product_category_id o ingredient_category_id.', 422);
        }

        if ($productCatId !== null && $ingredientCatId !== null) {
            throw new BudgetException('BUDGET_CATEGORY_TYPE_AMBIGUOUS', 'Solo puede indicarse un tipo de categoria a la vez.', 422);
        }
    }

    private function withSpent($cat, int $groupId, $budget): array
    {
        $spent = null;
        if ($cat->product_category_id !== null) {
            $spent = $this->repo->spentForProductCategory($groupId, $budget->year, $budget->month, $cat->product_category_id);
        }

        $amount    = (float) $cat->amount;
        $available = $spent !== null ? max(0, $amount - $spent) : null;

        return [
            'id'                     => $cat->id,
            'budget_id'              => $cat->budget_id,
            'product_category_id'    => $cat->product_category_id,
            'ingredient_category_id' => $cat->ingredient_category_id,
            'product_category_name'  => $cat->productCategory ? $cat->productCategory->name : null,
            'ingredient_category_name' => $cat->ingredientCategory ? $cat->ingredientCategory->name : null,
            'amount'                 => $amount,
            'spent_amount'           => $spent,
            'available_amount'       => $available,
            'status'                 => $cat->status,
            'created_at'             => $cat->created_at,
            'updated_at'             => $cat->updated_at,
        ];
    }
}
