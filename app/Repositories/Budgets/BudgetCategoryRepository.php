<?php

namespace App\Repositories\Budgets;

use App\Budget;
use App\BudgetCategory;
use App\Exceptions\Budgets\BudgetException;
use Illuminate\Support\Facades\DB;

class BudgetCategoryRepository
{
    public function listForBudget(int $budgetId)
    {
        return BudgetCategory::where('budget_id', $budgetId)
            ->where('status', 'active')
            ->with(['productCategory', 'ingredientCategory'])
            ->get();
    }

    public function findForBudget(int $budgetId, int $categoryId): BudgetCategory
    {
        $cat = BudgetCategory::where('budget_id', $budgetId)
            ->where('id', $categoryId)
            ->where('status', 'active')
            ->first();

        if (!$cat) {
            throw new BudgetException('BUDGET_CATEGORY_NOT_FOUND', 'La categoria de presupuesto no existe.', 404);
        }

        return $cat;
    }

    public function existsDuplicate(int $budgetId, ?int $productCatId, ?int $ingredientCatId, ?int $excludeId = null): bool
    {
        $query = BudgetCategory::where('budget_id', $budgetId)->where('status', 'active');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($productCatId !== null) {
            return $query->where('product_category_id', $productCatId)->exists();
        }

        return $query->where('ingredient_category_id', $ingredientCatId)->exists();
    }

    public function assignedTotal(int $budgetId, ?int $excludeId = null): float
    {
        $query = BudgetCategory::where('budget_id', $budgetId)->where('status', 'active');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return (float) $query->sum('amount');
    }

    public function create(array $data): BudgetCategory
    {
        return BudgetCategory::create($data);
    }

    public function update(BudgetCategory $cat, array $data): BudgetCategory
    {
        $cat->update($data);
        return $cat->fresh(['productCategory', 'ingredientCategory']);
    }

    public function deactivate(BudgetCategory $cat): BudgetCategory
    {
        $cat->update(['status' => 'inactive']);
        return $cat->fresh();
    }

    public function spentForProductCategory(int $groupId, int $year, int $month, int $productCategoryId): float
    {
        $result = DB::table('purchase_items as pi')
            ->join('purchases as pu', 'pu.id', '=', 'pi.purchase_id')
            ->join('products as p', 'p.id', '=', 'pi.product_id')
            ->where('pu.family_group_id', $groupId)
            ->whereYear('pu.purchase_date', $year)
            ->whereMonth('pu.purchase_date', $month)
            ->whereNotIn('pu.status', ['cancelled'])
            ->whereNull('pu.deleted_at')
            ->where('p.category_id', $productCategoryId)
            ->sum('pi.total_price');

        return round((float) $result, 2);
    }
}
