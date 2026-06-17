<?php

namespace App\Services\Budgets;

use App\Budget;
use App\Purchase;
use App\Repositories\Budgets\BudgetRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\ShoppingList;
use Illuminate\Support\Facades\DB;

class BudgetSummaryService
{
    private $budgetRepo;
    private $groupRepo;

    public function __construct(BudgetRepository $budgetRepo, FamilyGroupRepository $groupRepo)
    {
        $this->budgetRepo = $budgetRepo;
        $this->groupRepo  = $groupRepo;
    }

    public function summary(int $groupId, int $budgetId, int $userId): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->budgetRepo->findForGroup($groupId, $budgetId);

        $row = Purchase::where('family_group_id', $groupId)
            ->whereYear('purchase_date', $budget->year)
            ->whereMonth('purchase_date', $budget->month)
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(actual_total), 0) as spent, COUNT(*) as count')
            ->first();

        $spent     = round((float) $row->spent, 2);
        $total     = (float) $budget->total_amount;
        $available = max(0, $total - $spent);
        $percent   = $total > 0 ? round(($spent / $total) * 100, 2) : 0.0;

        return [
            'budget_id'         => $budget->id,
            'year'              => $budget->year,
            'month'             => $budget->month,
            'currency'          => $budget->currency,
            'total_amount'      => $total,
            'spent_amount'      => $spent,
            'available_amount'  => $available,
            'consumed_percent'  => $percent,
            'purchase_count'    => (int) $row->count,
        ];
    }

    public function projection(int $groupId, int $budgetId, int $userId): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $budget = $this->budgetRepo->findForGroup($groupId, $budgetId);

        $spent = round((float) Purchase::where('family_group_id', $groupId)
            ->whereYear('purchase_date', $budget->year)
            ->whereMonth('purchase_date', $budget->month)
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->sum('actual_total'), 2);

        $plannedSources = $this->plannedSources($groupId, $budget);
        $planned        = $plannedSources['total'];

        $total             = (float) $budget->total_amount;
        $availableProjected = max(0, $total - $spent - $planned);
        $percentProjected   = $total > 0 ? round((($spent + $planned) / $total) * 100, 2) : 0.0;

        return [
            'budget_id'              => $budget->id,
            'year'                   => $budget->year,
            'month'                  => $budget->month,
            'currency'               => $budget->currency,
            'total_amount'           => $total,
            'spent_amount'           => $spent,
            'planned_amount'         => $planned > 0 ? $planned : null,
            'available_projected'    => $availableProjected,
            'percent_projected'      => $percentProjected,
            'planned_sources'        => $plannedSources['sources'],
        ];
    }

    private function plannedSources(int $groupId, Budget $budget): array
    {
        $periodStart = sprintf('%04d-%02d-01', $budget->year, $budget->month);
        $periodEnd   = date('Y-m-t', strtotime($periodStart));

        $purchasedListIds = Purchase::where('family_group_id', $groupId)
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->whereNotNull('shopping_list_id')
            ->pluck('shopping_list_id')
            ->toArray();

        $lists = ShoppingList::where('family_group_id', $groupId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNull('deleted_at')
            ->whereNotNull('estimated_total')
            ->where(function ($q) use ($periodStart, $periodEnd, $groupId) {
                $q->whereBetween(DB::raw("DATE(created_at)"), [$periodStart, $periodEnd])
                  ->orWhereHas('mealPlan', function ($q2) use ($periodStart, $periodEnd) {
                      $q2->where('start_date', '<=', $periodEnd)
                         ->where('end_date', '>=', $periodStart)
                         ->whereNull('deleted_at');
                  });
            })
            ->when(!empty($purchasedListIds), function ($q) use ($purchasedListIds) {
                $q->whereNotIn('id', $purchasedListIds);
            })
            ->select('id', 'estimated_total', 'source_type', 'meal_plan_id')
            ->get();

        $seenMealPlanIds = [];
        $sources         = [];
        $total           = 0.0;

        foreach ($lists as $list) {
            if ($list->meal_plan_id !== null) {
                if (in_array($list->meal_plan_id, $seenMealPlanIds)) {
                    continue;
                }
                $seenMealPlanIds[] = $list->meal_plan_id;
            }

            $amount  = round((float) $list->estimated_total, 2);
            $total  += $amount;

            $sources[] = [
                'shopping_list_id' => $list->id,
                'source_type'      => $list->source_type,
                'meal_plan_id'     => $list->meal_plan_id,
                'estimated_total'  => $amount,
            ];
        }

        return ['total' => round($total, 2), 'sources' => $sources];
    }
}
