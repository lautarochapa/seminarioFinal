<?php

namespace App\Services\StockScan;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Product;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\HouseholdStock\HouseholdStockRepository;
use App\Repositories\StockScan\StockScanRepository;
use App\Services\HouseholdStock\StockEntrySuggestionService;
use App\StockItem;
use Illuminate\Support\Facades\DB;

class StockScanService
{
    private $groups;
    private $stock;
    private $scan;
    private $stockEntrySuggestions;

    public function __construct(
        FamilyGroupRepository $groups,
        HouseholdStockRepository $stock,
        StockScanRepository $scan,
        StockEntrySuggestionService $stockEntrySuggestions
    ) {
        $this->groups = $groups;
        $this->stock = $stock;
        $this->scan = $scan;
        $this->stockEntrySuggestions = $stockEntrySuggestions;
    }

    public function scan(int $groupId, int $userId, array $data, string $ip, string $ua): array
    {
        $this->groups->findOrFailForUser($groupId, $userId);

        $product = $this->scan->findActiveProductByBarcodeOrFail($data['barcode']);
        $locationId = (int) $data['stock_location_id'];

        if (! $this->stock->activeLocationInGroupExists($groupId, $locationId)) {
            throw new FamilyGroupException('STOCK_LOCATION_NOT_FOUND', 'Ubicacion de stock no encontrada.', 404);
        }

        $unitId = ! empty($data['unit_id'])
            ? (int) $data['unit_id']
            : $this->stockEntrySuggestions->forProduct($product, $groupId)['unit_id'];
        if (! $unitId) {
            throw new FamilyGroupException('STOCK_UNIT_REQUIRED', 'Selecciona una unidad para cargar este producto.', 422);
        }
        if (! $this->stock->activeUnitExists($unitId)) {
            throw new FamilyGroupException('STOCK_UNIT_INVALID', 'La unidad indicada no existe o no esta activa.', 422);
        }

        $expiration = ! empty($data['expiration_date']) ? $data['expiration_date'] : null;

        return DB::transaction(function () use ($groupId, $userId, $data, $locationId, $product, $unitId, $expiration, $ip, $ua) {
            $duplicate = StockItem::resolveActiveLot($groupId, (int) $product->id, $locationId, $unitId, $expiration);

            if ($duplicate) {
                $old = $this->payload($duplicate);
                $quantity = (float) $duplicate->quantity + (float) $data['quantity'];
                $updated = $this->stock->update($duplicate, [
                    'quantity' => $quantity,
                ])->fresh(['product.ingredient', 'location', 'unit']);
                $this->audit($userId, 'stock-item.updated', $updated->id, $old, $this->payload($updated), $ip, $ua);

                return [$updated, 200];
            }

            $create = [
                'family_group_id' => $groupId,
                'product_id' => $product->id,
                'stock_location_id' => $locationId,
                'quantity' => $data['quantity'],
                'unit_id' => $unitId,
                'status' => 'active',
            ];
            if ($expiration !== null) {
                $create['expiration_date'] = $expiration;
            }

            $item = $this->stock->create($create)->fresh(['product.ingredient', 'location', 'unit']);

            $this->audit($userId, 'stock-item.created', $item->id, null, $this->payload($item), $ip, $ua);

            return [$item, 201];
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
