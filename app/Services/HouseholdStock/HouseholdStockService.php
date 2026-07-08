<?php

namespace App\Services\HouseholdStock;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Repositories\FamilyGroup\FamilyGroupMemberRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\HouseholdStock\HouseholdStockRepository;
use App\StockItem;
use Illuminate\Support\Facades\DB;

class HouseholdStockService
{
    private $groups;
    private $members;
    private $stock;

    public function __construct(
        FamilyGroupRepository $groups,
        FamilyGroupMemberRepository $members,
        HouseholdStockRepository $stock
    ) {
        $this->groups = $groups;
        $this->members = $members;
        $this->stock = $stock;
    }

    public function list(int $groupId, int $userId, array $filters)
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        return $this->stock->paginateForGroup($groupId, $filters);
    }

    public function create(int $groupId, int $userId, array $data, string $ip, string $ua)
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);
        $data = $this->prepare($data);
        $this->validateRelations($groupId, $data);

        return DB::transaction(function () use ($groupId, $userId, $data, $ip, $ua) {
            $duplicate = $this->stock->findDuplicate(
                $groupId,
                (int) $data['product_id'],
                $data['stock_location_id'] ?? null
            );

            if ($duplicate) {
                $old = $this->payload($duplicate);
                $quantity = (float) $duplicate->quantity + (float) $data['quantity'];
                $updated = $this->stock->update($duplicate, array_merge($data, ['quantity' => $quantity]));
                $new = $this->payload($updated);
                $this->audit($userId, 'stock-item.updated', $updated->id, $old, $new, $ip, $ua);

                return [$updated, 200];
            }

            $item = $this->stock->create(array_merge($data, [
                'family_group_id' => $groupId,
                'status' => $data['status'] ?? 'active',
            ]));

            $item = $item->fresh(['product', 'location', 'unit']);
            $this->audit($userId, 'stock-item.created', $item->id, null, $this->payload($item), $ip, $ua);

            return [$item, 201];
        });
    }

    public function update(int $groupId, int $stockItemId, int $userId, array $data, string $ip, string $ua): StockItem
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);
        $item = $this->stock->findInGroupOrFail($groupId, $stockItemId);
        $data = $this->prepare($data, false);
        $this->validateRelations($groupId, $data);

        return DB::transaction(function () use ($item, $userId, $data, $ip, $ua) {
            $old = $this->payload($item);
            $updated = $this->stock->update($item, $data);
            $new = $this->payload($updated);

            if ($old != $new) {
                $this->audit($userId, 'stock-item.updated', $updated->id, $old, $new, $ip, $ua);
            }

            return $updated;
        });
    }

    public function delete(int $groupId, int $stockItemId, int $userId, string $ip, string $ua): StockItem
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);
        $item = $this->stock->findInGroupOrFail($groupId, $stockItemId);

        return DB::transaction(function () use ($item, $userId, $ip, $ua) {
            $old = $this->payload($item);
            $item->status = 'inactive';
            $item->save();
            $deleted = $this->stock->delete($item);
            $new = $this->payload($deleted);
            $this->audit($userId, 'stock-item.deleted', $deleted->id, $old, $new, $ip, $ua);

            return $deleted;
        });
    }

    public function summary(int $groupId, int $userId): array
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $items = $this->stock->activeItemsForGroup($groupId);
        $limit = now()->addDays(7)->toDateString();

        return [
            'total_items' => $items->count(),
            'distinct_products' => $items->pluck('product_id')->unique()->count(),
            'items_by_location' => $items->groupBy('stock_location_id')->map(function ($group) {
                $first = $group->first();
                return [
                    'location_id' => $first->stock_location_id,
                    'location_name' => $first->location ? $first->location->name : null,
                    'total_items' => $group->count(),
                ];
            })->values()->all(),
            'expiring_soon' => $items->filter(function ($item) use ($limit) {
                return $item->expiration_date && $item->expiration_date->toDateString() <= $limit;
            })->count(),
        ];
    }

    public function value(int $groupId, int $userId): array
    {
        $this->groups->findOrFailForUser($groupId, $userId);
        $items = $this->stock->activeItemsForGroup($groupId)->filter(function ($item) {
            return $item->estimated_purchase_price !== null;
        });

        $total = $items->reduce(function ($carry, $item) {
            return $carry + ((float) $item->quantity * (float) $item->estimated_purchase_price);
        }, 0.0);

        return [
            'total_value' => round($total, 2),
            'currency' => 'ARS',
            'valued_items' => $items->count(),
        ];
    }

    private function requireAdminOrOwner(int $groupId, int $userId): void
    {
        $membership = $this->members->findMembership($groupId, $userId);

        if (! $membership || ! in_array($membership->role_in_group, ['owner', 'admin'])) {
            throw new FamilyGroupException('FAMILY_GROUP_ACCESS_DENIED', 'Se requiere rol de propietario o administrador.', 403);
        }
    }

    private function validateRelations(int $groupId, array $data): void
    {
        if (isset($data['product_id']) && ! $this->stock->activeProductExists((int) $data['product_id'], $groupId)) {
            throw new FamilyGroupException('STOCK_PRODUCT_INVALID', 'El producto indicado no existe o no esta activo.', 422);
        }

        if (isset($data['stock_location_id']) && ! $this->stock->activeLocationInGroupExists($groupId, (int) $data['stock_location_id'])) {
            throw new FamilyGroupException('STOCK_LOCATION_NOT_FOUND', 'Ubicacion de stock no encontrada.', 404);
        }

        if (isset($data['unit_id']) && ! $this->stock->activeUnitExists((int) $data['unit_id'])) {
            throw new FamilyGroupException('STOCK_UNIT_INVALID', 'La unidad indicada no existe o no esta activa.', 422);
        }
    }

    private function prepare(array $data, bool $creating = true): array
    {
        if (array_key_exists('purchase_price', $data)) {
            $data['estimated_purchase_price'] = $data['purchase_price'];
            unset($data['purchase_price']);
        }

        return array_filter(array_intersect_key($data, array_flip([
            'product_id',
            'stock_location_id',
            'quantity',
            'unit_id',
            'expiration_date',
            'estimated_purchase_price',
            'status',
        ])), function ($value) {
            return $value !== null;
        });
    }

    private function payload(StockItem $item): array
    {
        return [
            'family_group_id' => $item->family_group_id,
            'product_id' => $item->product_id,
            'stock_location_id' => $item->stock_location_id,
            'quantity' => $item->quantity,
            'unit_id' => $item->unit_id,
            'expiration_date' => $item->expiration_date ? $item->expiration_date->toDateString() : null,
            'estimated_purchase_price' => $item->estimated_purchase_price,
            'status' => $item->status,
            'deleted_at' => $item->deleted_at ? (string) $item->deleted_at : null,
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
