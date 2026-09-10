<?php

namespace App\Services\StockScan;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Product;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\HouseholdStock\HouseholdStockRepository;
use App\Repositories\StockScan\StockScanRepository;
use App\StockItem;
use Illuminate\Support\Facades\DB;

class StockScanService
{
    private $groups;
    private $stock;
    private $scan;

    public function __construct(
        FamilyGroupRepository $groups,
        HouseholdStockRepository $stock,
        StockScanRepository $scan
    ) {
        $this->groups = $groups;
        $this->stock = $stock;
        $this->scan = $scan;
    }

    public function scan(int $groupId, int $userId, array $data, string $ip, string $ua): array
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        $product = $this->scan->findActiveProductByBarcodeOrFail($data['barcode']);
        $locationId = (int) $data['stock_location_id'];

        if (! $this->stock->activeLocationInGroupExists($groupId, $locationId)) {
            throw new FamilyGroupException('STOCK_LOCATION_NOT_FOUND', 'Ubicacion de stock no encontrada.', 404);
        }

        $unitId = ! empty($data['unit_id']) ? (int) $data['unit_id'] : $this->resolveUnitId($product);
        if (! $unitId || ! $this->stock->activeUnitExists($unitId)) {
            throw new FamilyGroupException('STOCK_UNIT_INVALID', 'La unidad indicada no existe o no esta activa.', 422);
        }

        return DB::transaction(function () use ($groupId, $userId, $data, $locationId, $product, $unitId, $ip, $ua) {
            $duplicate = StockItem::resolveActiveLot($groupId, (int) $product->id, $locationId, $unitId, null);

            if ($duplicate) {
                $old = $this->payload($duplicate);
                $quantity = (float) $duplicate->quantity + (float) $data['quantity'];
                $updated = $this->stock->update($duplicate, [
                    'quantity' => $quantity,
                ])->fresh(['product.ingredient', 'location', 'unit']);
                $this->audit($userId, 'stock-item.updated', $updated->id, $old, $this->payload($updated), $ip, $ua);

                return [$updated, 200];
            }

            $item = $this->stock->create([
                'family_group_id' => $groupId,
                'product_id' => $product->id,
                'stock_location_id' => $locationId,
                'quantity' => $data['quantity'],
                'unit_id' => $unitId,
                'status' => 'active',
            ])->fresh(['product.ingredient', 'location', 'unit']);

            $this->audit($userId, 'stock-item.created', $item->id, null, $this->payload($item), $ip, $ua);

            return [$item, 201];
        });
    }

    private function resolveUnitId(Product $product): ?int
    {
        if ($product->default_unit_id) {
            return (int) $product->default_unit_id;
        }

        if ($product->ingredient && $product->ingredient->base_unit_id) {
            return (int) $product->ingredient->base_unit_id;
        }

        return null;
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
