<?php

namespace App\Services\StockMovements;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Repositories\FamilyGroup\FamilyGroupMemberRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\HouseholdStock\HouseholdStockRepository;
use App\Repositories\StockMovements\StockMovementRepository;
use App\StockItem;
use Illuminate\Support\Facades\DB;

class StockMovementService
{
    private $groups;
    private $members;
    private $stock;
    private $movements;

    public function __construct(
        FamilyGroupRepository $groups,
        FamilyGroupMemberRepository $members,
        HouseholdStockRepository $stock,
        StockMovementRepository $movements
    ) {
        $this->groups = $groups;
        $this->members = $members;
        $this->stock = $stock;
        $this->movements = $movements;
    }

    public function list(int $groupId, int $userId, array $filters)
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        return $this->movements->paginateForGroup($groupId, $filters);
    }

    public function adjust(int $groupId, int $stockItemId, int $userId, array $data, string $ip, string $ua): StockItem
    {
        $mode = $data['mode'] ?? 'set';
        $quantity = (float) $data['quantity'];

        return $this->change($groupId, $stockItemId, $userId, 'adjustment', $quantity, $mode, $data['reason'], $ip, $ua);
    }

    public function consume(int $groupId, int $stockItemId, int $userId, array $data, string $ip, string $ua): StockItem
    {
        return $this->change($groupId, $stockItemId, $userId, 'consumption', (float) $data['quantity'], 'subtract', $data['reason'] ?? null, $ip, $ua);
    }

    public function discard(int $groupId, int $stockItemId, int $userId, array $data, string $ip, string $ua): StockItem
    {
        return $this->change($groupId, $stockItemId, $userId, 'discard', (float) $data['quantity'], 'subtract', $data['reason'], $ip, $ua);
    }

    private function change(int $groupId, int $stockItemId, int $userId, string $type, float $quantity, string $mode, ?string $reason, string $ip, string $ua): StockItem
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);
        $item = $this->stock->findInGroupOrFail($groupId, $stockItemId);

        return DB::transaction(function () use ($item, $groupId, $userId, $type, $quantity, $mode, $reason, $ip, $ua) {
            $old = $this->payload($item);
            $oldQuantity = (float) $item->quantity;
            $newQuantity = $this->newQuantity($oldQuantity, $quantity, $mode);
            $delta = $newQuantity - $oldQuantity;

            if ($newQuantity < 0) {
                throw new FamilyGroupException('STOCK_INSUFFICIENT_QUANTITY', 'La cantidad supera el stock disponible.', 409);
            }

            $updated = $this->stock->update($item, ['quantity' => $newQuantity]);

            $this->movements->create([
                'family_group_id' => $groupId,
                'stock_item_id' => $updated->id,
                'product_id' => $updated->product_id,
                'movement_type' => $type,
                'quantity' => $delta,
                'unit_id' => $updated->unit_id,
                'reason' => $reason,
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            $updated = $updated->fresh(['product', 'location', 'unit']);
            $this->audit($userId, $this->actionFor($type), $updated->id, $old, $this->payload($updated), $ip, $ua);

            return $updated;
        });
    }

    private function newQuantity(float $oldQuantity, float $quantity, string $mode): float
    {
        if ($mode === 'add') {
            return $oldQuantity + $quantity;
        }

        if ($mode === 'subtract') {
            return $oldQuantity - $quantity;
        }

        return $quantity;
    }

    private function requireAdminOrOwner(int $groupId, int $userId): void
    {
        $membership = $this->members->findMembership($groupId, $userId);

        if (! $membership || ! in_array($membership->role_in_group, ['owner', 'admin'])) {
            throw new FamilyGroupException('FAMILY_GROUP_ACCESS_DENIED', 'Se requiere rol de propietario o administrador.', 403);
        }
    }

    private function actionFor(string $type): string
    {
        if ($type === 'consumption') {
            return 'stock-item.consumed';
        }

        if ($type === 'discard') {
            return 'stock-item.discarded';
        }

        return 'stock-item.adjusted';
    }

    private function payload(StockItem $item): array
    {
        return [
            'family_group_id' => $item->family_group_id,
            'product_id' => $item->product_id,
            'stock_location_id' => $item->stock_location_id,
            'quantity' => $item->quantity,
            'unit_id' => $item->unit_id,
            'status' => $item->status,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $ua): void
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'stock_items',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }
}
