<?php

namespace App\Services\GroupReports;

use App\Budget;
use App\Purchase;
use App\RecipeCookLog;
use App\RecipeNutrition;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\WasteReports\WasteReportRepository;
use App\StockItem;
use Illuminate\Support\Facades\DB;

class GroupReportService
{
    private $groupRepo;
    private $wasteRepo;

    public function __construct(FamilyGroupRepository $groupRepo, WasteReportRepository $wasteRepo)
    {
        $this->groupRepo = $groupRepo;
        $this->wasteRepo = $wasteRepo;
    }

    public function stock(int $groupId, int $userId, array $filters): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $rows = DB::table('stock_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.category_id')
            ->where('si.family_group_id', $groupId)
            ->where('si.status', 'active')
            ->whereNull('si.deleted_at')
            ->select(
                'pc.id as category_id',
                'pc.name as category_name',
                DB::raw('COUNT(si.id) as item_count'),
                DB::raw('COUNT(DISTINCT si.product_id) as product_count')
            )
            ->groupBy('pc.id', 'pc.name')
            ->get();

        $total = DB::table('stock_items')
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();

        return [
            'total_items'    => $total,
            'by_category'    => $rows->toArray(),
        ];
    }

    public function stockValue(int $groupId, int $userId, array $filters): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $row = DB::table('stock_items')
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotNull('estimated_purchase_price')
            ->selectRaw('SUM(quantity * estimated_purchase_price) as total_value, COUNT(*) as items_with_price')
            ->first();

        $total_items = DB::table('stock_items')
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();

        return [
            'total_value'       => $row->total_value !== null ? round((float) $row->total_value, 2) : null,
            'items_with_price'  => (int) $row->items_with_price,
            'items_without_price' => $total_items - (int) $row->items_with_price,
            'currency_note'     => 'Los precios son estimados y pueden mezclar monedas distintas.',
        ];
    }

    public function expiringProducts(int $groupId, int $userId, array $filters): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $days    = max(1, (int) ($filters['days'] ?? 7));
        $dateMax = now()->addDays($days)->toDateString();
        $today   = now()->toDateString();

        $items = DB::table('stock_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.family_group_id', $groupId)
            ->where('si.status', 'active')
            ->whereNull('si.deleted_at')
            ->whereNotNull('si.expiration_date')
            ->whereBetween('si.expiration_date', [$today, $dateMax])
            ->select('si.id', 'p.name as product_name', 'si.expiration_date', 'si.quantity', 'si.estimated_purchase_price')
            ->orderBy('si.expiration_date')
            ->get();

        $expired = DB::table('stock_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.family_group_id', $groupId)
            ->where('si.status', 'active')
            ->whereNull('si.deleted_at')
            ->whereNotNull('si.expiration_date')
            ->where('si.expiration_date', '<', $today)
            ->count();

        return [
            'days_window'    => $days,
            'expiring_count' => $items->count(),
            'already_expired_count' => $expired,
            'items'          => $items->toArray(),
        ];
    }

    public function waste(int $groupId, int $userId, array $filters): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $totals = $this->wasteRepo->totals($groupId, $filters);

        $monthly = DB::table('stock_movements')
            ->where('family_group_id', $groupId)
            ->whereIn('movement_type', ['discard', 'expiration'])
            ->when(!empty($filters['date_from']), function ($q) use ($filters) {
                $q->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when(!empty($filters['date_to']), function ($q) use ($filters) {
                $q->whereDate('created_at', '<=', $filters['date_to']);
            })
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month, COUNT(*) as count, SUM(ABS(quantity)) as total_quantity")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return array_merge($totals, ['by_month' => $monthly->toArray()]);
    }

    public function purchases(int $groupId, int $userId, array $filters): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $query = Purchase::where('family_group_id', $groupId)
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at');

        if (!empty($filters['date_from'])) {
            $query->whereDate('purchase_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('purchase_date', '<=', $filters['date_to']);
        }

        $totals = (clone $query)
            ->selectRaw('COALESCE(SUM(actual_total), 0) as total_spent, COUNT(*) as total_count')
            ->first();

        $monthly = (clone $query)
            ->selectRaw("TO_CHAR(purchase_date, 'YYYY-MM') as month, COUNT(*) as count, SUM(actual_total) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'total_spent'  => round((float) $totals->total_spent, 2),
            'total_count'  => (int) $totals->total_count,
            'by_month'     => $monthly->toArray(),
        ];
    }

    public function budget(int $groupId, int $userId, array $filters): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $budgets = Budget::where('family_group_id', $groupId)
            ->where('status', 'active')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get(['id', 'year', 'month', 'total_amount', 'currency', 'status']);

        return [
            'total_budgets' => $budgets->count(),
            'budgets'       => $budgets->toArray(),
        ];
    }

    public function budgetVsActual(int $groupId, int $userId, array $filters): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $budgets = Budget::where('family_group_id', $groupId)
            ->where('status', 'active')
            ->get();

        $rows = [];
        foreach ($budgets as $budget) {
            $spent = (float) Purchase::where('family_group_id', $groupId)
                ->whereYear('purchase_date', $budget->year)
                ->whereMonth('purchase_date', $budget->month)
                ->whereNotIn('status', ['cancelled'])
                ->whereNull('deleted_at')
                ->sum('actual_total');

            $total     = (float) $budget->total_amount;
            $available = max(0, $total - $spent);
            $percent   = $total > 0 ? round(($spent / $total) * 100, 2) : null;

            $rows[] = [
                'budget_id'      => $budget->id,
                'year'           => $budget->year,
                'month'          => $budget->month,
                'currency'       => $budget->currency,
                'total_amount'   => $total,
                'spent_amount'   => round($spent, 2),
                'available'      => round($available, 2),
                'consumed_percent' => $percent,
            ];
        }

        return ['periods' => $rows];
    }

    public function recipesCooked(int $groupId, int $userId, array $filters): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $query = RecipeCookLog::where('family_group_id', $groupId);

        if (!empty($filters['date_from'])) {
            $query->whereDate('cooked_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('cooked_at', '<=', $filters['date_to']);
        }

        $totals = (clone $query)
            ->selectRaw('COUNT(*) as total_cooked, COALESCE(SUM(servings), 0) as total_servings')
            ->first();

        $top = (clone $query)
            ->join('recipes as r', 'r.id', '=', 'recipe_cook_logs.recipe_id')
            ->select('recipe_cook_logs.recipe_id', 'r.name as recipe_name', DB::raw('COUNT(*) as times_cooked'))
            ->groupBy('recipe_cook_logs.recipe_id', 'r.name')
            ->orderByDesc('times_cooked')
            ->limit(10)
            ->get();

        return [
            'total_cooked'   => (int) $totals->total_cooked,
            'total_servings' => (int) $totals->total_servings,
            'top_recipes'    => $top->toArray(),
        ];
    }

    public function nutritionEstimate(int $groupId, int $userId, array $filters): array
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $query = DB::table('recipe_cook_logs as rcl')
            ->join('recipe_nutrition as rn', 'rn.recipe_id', '=', 'rcl.recipe_id')
            ->where('rcl.family_group_id', $groupId)
            ->whereNotNull('rn.calories_per_serving');

        if (!empty($filters['date_from'])) {
            $query->whereDate('rcl.cooked_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('rcl.cooked_at', '<=', $filters['date_to']);
        }

        $totals = (clone $query)
            ->selectRaw('
                COUNT(*) as logs_with_nutrition,
                SUM(rn.calories_per_serving * rcl.servings) as total_calories,
                SUM(rn.protein_per_serving * rcl.servings) as total_protein,
                SUM(rn.carbohydrates_per_serving * rcl.servings) as total_carbohydrates,
                SUM(rn.fat_per_serving * rcl.servings) as total_fat
            ')
            ->first();

        $totalLogs = RecipeCookLog::where('family_group_id', $groupId)
            ->when(!empty($filters['date_from']), function ($q) use ($filters) {
                $q->whereDate('cooked_at', '>=', $filters['date_from']);
            })
            ->when(!empty($filters['date_to']), function ($q) use ($filters) {
                $q->whereDate('cooked_at', '<=', $filters['date_to']);
            })
            ->count();

        $withNutrition = (int) $totals->logs_with_nutrition;

        return [
            'estimated_calories'      => $totals->total_calories !== null ? round((float) $totals->total_calories, 2) : null,
            'estimated_protein_g'     => $totals->total_protein !== null ? round((float) $totals->total_protein, 2) : null,
            'estimated_carbs_g'       => $totals->total_carbohydrates !== null ? round((float) $totals->total_carbohydrates, 2) : null,
            'estimated_fat_g'         => $totals->total_fat !== null ? round((float) $totals->total_fat, 2) : null,
            'logs_with_nutrition'     => $withNutrition,
            'logs_without_nutrition'  => $totalLogs - $withNutrition,
            'note'                    => 'Estimacion basada solo en recetas cocinadas con informacion nutricional disponible. No constituye asesoramiento medico.',
        ];
    }
}
