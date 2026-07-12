<?php

namespace App\Services\StockAlerts;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Product;
use App\Repositories\FamilyGroup\FamilyGroupMemberRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\HouseholdStock\HouseholdStockRepository;
use App\Repositories\StockAlerts\StockAlertRepository;
use App\StockAlert;
use App\StockMinimumRule;
use Illuminate\Support\Facades\DB;

class StockAlertService
{
    private $groups;
    private $members;
    private $stock;
    private $alerts;

    public function __construct(
        FamilyGroupRepository $groups,
        FamilyGroupMemberRepository $members,
        HouseholdStockRepository $stock,
        StockAlertRepository $alerts
    ) {
        $this->groups = $groups;
        $this->members = $members;
        $this->stock = $stock;
        $this->alerts = $alerts;
    }

    public function expiring(int $groupId, int $userId, array $filters)
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        return $this->alerts->expiring($groupId, $filters);
    }

    public function lowStock(int $groupId, int $userId, array $filters)
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        return $this->alerts->lowStock($groupId, $filters);
    }

    public function alerts(int $groupId, int $userId, array $filters)
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        return $this->alerts->alerts($groupId, $filters);
    }

    public function markRead(int $groupId, int $alertId, int $userId, string $ip, string $ua): StockAlert
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $alert = $this->alerts->findAlertInGroupOrFail($groupId, $alertId);

        return DB::transaction(function () use ($alert, $userId, $ip, $ua) {
            $old = $this->alertPayload($alert);
            $alert->status = 'read';
            $alert->read_at = now();
            $alert->save();
            $alert = $alert->fresh(['product', 'stockItem.location']);
            $this->audit($userId, 'stock-alert.read', 'stock_alerts', $alert->id, $old, $this->alertPayload($alert), $ip, $ua);

            return $alert;
        });
    }

    public function rules(int $groupId, int $userId, array $filters)
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        return $this->alerts->rules($groupId, $filters);
    }

    public function showRule(int $groupId, int $ruleId, int $userId): StockMinimumRule
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        return $this->alerts->findRuleInGroupOrFail($groupId, $ruleId);
    }

    public function createRule(int $groupId, int $userId, array $data, string $ip, string $ua): StockMinimumRule
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);
        $data = $this->prepareRule($data);
        $this->validateRule($groupId, $data);

        return DB::transaction(function () use ($groupId, $userId, $data, $ip, $ua) {
            $rule = StockMinimumRule::create(array_merge($data, [
                'family_group_id' => $groupId,
                'status' => $data['status'] ?? 'active',
            ]))->fresh(['product', 'ingredient', 'unit']);

            $this->audit($userId, 'stock-minimum-rule.created', 'stock_minimum_rules', $rule->id, null, $this->rulePayload($rule), $ip, $ua);

            return $rule;
        });
    }

    public function updateRule(int $groupId, int $ruleId, int $userId, array $data, string $ip, string $ua): StockMinimumRule
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);
        $rule = $this->alerts->findRuleInGroupOrFail($groupId, $ruleId);
        $data = $this->prepareRule($data, false);
        $merged = array_merge($rule->only(['product_id', 'ingredient_id', 'minimum_quantity', 'unit_id', 'status']), $data);
        $this->validateRule($groupId, $merged, $rule->id);

        return DB::transaction(function () use ($rule, $userId, $data, $ip, $ua) {
            $old = $this->rulePayload($rule);
            $rule->fill($data);
            $rule->save();
            $rule = $rule->fresh(['product', 'ingredient', 'unit']);
            $this->audit($userId, 'stock-minimum-rule.updated', 'stock_minimum_rules', $rule->id, $old, $this->rulePayload($rule), $ip, $ua);

            return $rule;
        });
    }

    public function deleteRule(int $groupId, int $ruleId, int $userId, string $ip, string $ua): StockMinimumRule
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);
        $rule = $this->alerts->findRuleInGroupOrFail($groupId, $ruleId);

        return DB::transaction(function () use ($rule, $userId, $ip, $ua) {
            $old = $this->rulePayload($rule);
            $rule->status = 'inactive';
            $rule->save();
            $rule = $rule->fresh(['product', 'ingredient', 'unit']);
            $this->audit($userId, 'stock-minimum-rule.deleted', 'stock_minimum_rules', $rule->id, $old, $this->rulePayload($rule), $ip, $ua);

            return $rule;
        });
    }

    private function validateRule(int $groupId, array $data, ?int $ignoreId = null): void
    {
        $productId = ! empty($data['product_id']) ? (int) $data['product_id'] : null;
        $ingredientId = ! empty($data['ingredient_id']) ? (int) $data['ingredient_id'] : null;

        if (! $productId && ! $ingredientId) {
            throw new FamilyGroupException('STOCK_MINIMUM_RULE_TARGET_REQUIRED', 'Debe indicar producto o ingrediente.', 422);
        }

        if ($productId && ! $this->stock->activeProductExists($productId)) {
            throw new FamilyGroupException('STOCK_PRODUCT_INVALID', 'El producto indicado no existe o no esta activo.', 422);
        }

        if (! $this->stock->activeUnitExists((int) $data['unit_id'])) {
            throw new FamilyGroupException('STOCK_UNIT_INVALID', 'La unidad indicada no existe o no esta activa.', 422);
        }

        if ($this->alerts->activeRuleExists($groupId, $productId, $ingredientId, $ignoreId)) {
            throw new FamilyGroupException('STOCK_MINIMUM_RULE_ALREADY_EXISTS', 'Ya existe una regla activa para ese objetivo.', 409);
        }
    }

    private function requireAdminOrOwner(int $groupId, int $userId): void
    {
        $membership = $this->members->findMembership($groupId, $userId);

        if (! $membership || ! in_array($membership->role_in_group, ['owner', 'admin'])) {
            throw new FamilyGroupException('FAMILY_GROUP_ACCESS_DENIED', 'Se requiere rol de propietario o administrador.', 403);
        }
    }

    private function prepareRule(array $data, bool $creating = true): array
    {
        $allowed = ['product_id', 'ingredient_id', 'minimum_quantity', 'unit_id', 'status'];

        return array_filter(array_intersect_key($data, array_flip($allowed)), function ($value) {
            return $value !== null;
        });
    }

    private function rulePayload(StockMinimumRule $rule): array
    {
        return [
            'family_group_id' => $rule->family_group_id,
            'product_id' => $rule->product_id,
            'ingredient_id' => $rule->ingredient_id,
            'minimum_quantity' => $rule->minimum_quantity,
            'unit_id' => $rule->unit_id,
            'status' => $rule->status,
        ];
    }

    private function alertPayload(StockAlert $alert): array
    {
        return [
            'family_group_id' => $alert->family_group_id,
            'stock_item_id' => $alert->stock_item_id,
            'product_id' => $alert->product_id,
            'alert_type' => $alert->alert_type,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'read_at' => $alert->read_at ? (string) $alert->read_at : null,
        ];
    }

    private function audit($actorId, $action, $entityName, $entityId, $old, $new, $ip, $ua): void
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => $entityName,
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }
}
