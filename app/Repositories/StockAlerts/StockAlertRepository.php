<?php

namespace App\Repositories\StockAlerts;

use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\StockAlert;
use App\StockItem;
use App\StockMinimumRule;

class StockAlertRepository
{
    public function expiring(int $groupId, array $filters)
    {
        $days = min(max((int) ($filters['days'] ?? 7), 1), 365);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return StockItem::with(['product', 'location', 'unit'])
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('expiration_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function lowStock(int $groupId, array $filters)
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return StockItem::with(['product', 'location', 'unit'])
            ->join('stock_minimum_rules', function ($join) {
                $join->on('stock_minimum_rules.product_id', '=', 'stock_items.product_id')
                    ->on('stock_minimum_rules.family_group_id', '=', 'stock_items.family_group_id');
            })
            ->where('stock_items.family_group_id', $groupId)
            ->where('stock_items.status', 'active')
            ->whereNull('stock_items.deleted_at')
            ->where('stock_minimum_rules.status', 'active')
            ->whereColumn('stock_items.quantity', '<', 'stock_minimum_rules.minimum_quantity')
            ->select('stock_items.*')
            ->orderBy('stock_items.quantity')
            ->orderByDesc('stock_items.id')
            ->paginate($perPage);
    }

    public function alerts(int $groupId, array $filters)
    {
        $query = StockAlert::with(['product', 'stockItem.location'])
            ->where('family_group_id', $groupId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('alert_type', $filters['type']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }

    public function findAlertInGroupOrFail(int $groupId, int $alertId): StockAlert
    {
        $alert = StockAlert::with(['product', 'stockItem.location'])
            ->where('family_group_id', $groupId)
            ->where('id', $alertId)
            ->first();

        if (! $alert) {
            throw new FamilyGroupException('STOCK_ALERT_NOT_FOUND', 'Alerta de stock no encontrada.', 404);
        }

        return $alert;
    }

    public function rules(int $groupId, array $filters)
    {
        $query = StockMinimumRule::with(['product', 'ingredient', 'unit'])
            ->where('family_group_id', $groupId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function findRuleInGroupOrFail(int $groupId, int $ruleId): StockMinimumRule
    {
        $rule = StockMinimumRule::with(['product', 'ingredient', 'unit'])
            ->where('family_group_id', $groupId)
            ->where('id', $ruleId)
            ->first();

        if (! $rule) {
            throw new FamilyGroupException('STOCK_MINIMUM_RULE_NOT_FOUND', 'Regla de stock minimo no encontrada.', 404);
        }

        return $rule;
    }

    public function activeRuleExists(int $groupId, ?int $productId, ?int $ingredientId, ?int $ignoreId = null): bool
    {
        $query = StockMinimumRule::where('family_group_id', $groupId)
            ->where('status', 'active');

        if ($productId) {
            $query->where('product_id', $productId);
        } else {
            $query->whereNull('product_id');
        }

        if ($ingredientId) {
            $query->where('ingredient_id', $ingredientId);
        } else {
            $query->whereNull('ingredient_id');
        }

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
