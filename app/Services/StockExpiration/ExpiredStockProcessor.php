<?php

namespace App\Services\StockExpiration;

use App\AuditLog;
use App\FamilyGroupMember;
use App\Notification;
use App\Repositories\StockExpiration\ExpiredStockRepository;
use App\StockAlert;
use App\StockItem;
use App\StockMovement;
use App\StockWasteLog;
use Illuminate\Support\Facades\DB;

class ExpiredStockProcessor
{
    private $items;

    public function __construct(ExpiredStockRepository $items)
    {
        $this->items = $items;
    }

    public function process(int $limit = 200): array
    {
        $candidates = $this->items->pendingExpiredItems($limit);
        $summary = [
            'processed' => 0,
            'skipped' => 0,
            'discarded_quantity' => 0.0,
            'estimated_loss' => 0.0,
        ];

        foreach ($candidates as $candidate) {
            $result = $this->processOne((int) $candidate->id);

            if (! $result['processed']) {
                $summary['skipped']++;
                continue;
            }

            $summary['processed']++;
            $summary['discarded_quantity'] += $result['quantity'];
            $summary['estimated_loss'] += $result['estimated_loss'];
        }

        $summary['discarded_quantity'] = round($summary['discarded_quantity'], 4);
        $summary['estimated_loss'] = round($summary['estimated_loss'], 2);

        return $summary;
    }

    public function processOne(int $stockItemId): array
    {
        return DB::transaction(function () use ($stockItemId) {
            if ($this->items->alreadyProcessed($stockItemId)) {
                return $this->skipped();
            }

            $item = $this->items->lockStockItem($stockItemId);

            if (! $this->isProcessable($item)) {
                return $this->skipped();
            }

            $old = $this->payload($item);
            $quantity = (float) $item->quantity;
            $estimatedLoss = $this->estimatedLoss($item, $quantity);

            StockMovement::create([
                'family_group_id' => $item->family_group_id,
                'stock_item_id' => $item->id,
                'product_id' => $item->product_id,
                'movement_type' => 'expiration',
                'quantity' => -1 * $quantity,
                'unit_id' => $item->unit_id,
                'reason' => 'Procesado automaticamente por vencimiento',
                'created_by' => null,
                'created_at' => now(),
            ]);

            StockWasteLog::create([
                'family_group_id' => $item->family_group_id,
                'product_id' => $item->product_id,
                'stock_item_id' => $item->id,
                'quantity' => $quantity,
                'unit_id' => $item->unit_id,
                'estimated_loss_amount' => $estimatedLoss,
                'expiration_date' => $item->expiration_date,
                'processed_at' => now(),
                'created_at' => now(),
            ]);

            $item->quantity = 0;
            $item->status = 'expired';
            $item->save();

            $fresh = $item->fresh(['product']);
            $this->createStockAlert($fresh);
            $this->createNotifications($fresh);
            $this->audit($fresh, $old, $this->payload($fresh));

            return [
                'processed' => true,
                'quantity' => $quantity,
                'estimated_loss' => $estimatedLoss,
            ];
        });
    }

    private function isProcessable(?StockItem $item): bool
    {
        if (! $item || $item->deleted_at !== null) {
            return false;
        }

        if ($item->status !== 'active' || (float) $item->quantity <= 0) {
            return false;
        }

        return $item->expiration_date && $item->expiration_date->toDateString() < now()->toDateString();
    }

    private function estimatedLoss(StockItem $item, float $quantity): float
    {
        if ($item->estimated_purchase_price === null) {
            return 0.0;
        }

        return round($quantity * (float) $item->estimated_purchase_price, 2);
    }

    private function createStockAlert(StockItem $item): void
    {
        $alert = StockAlert::firstOrCreate(
            [
                'family_group_id' => $item->family_group_id,
                'stock_item_id' => $item->id,
                'alert_type' => 'expired_stock_processed',
            ],
            [
                'product_id' => $item->product_id,
                'message' => 'Se descarto automaticamente stock vencido.',
                'severity' => 'high',
                'status' => 'open',
            ]
        );

        if (! $alert->created_at) {
            $alert->created_at = now();
            $alert->save();
        }
    }

    private function createNotifications(StockItem $item): void
    {
        $members = FamilyGroupMember::where('family_group_id', $item->family_group_id)
            ->where('status', 'active')
            ->pluck('user_id');

        foreach ($members as $userId) {
            Notification::create([
                'user_id' => $userId,
                'family_group_id' => $item->family_group_id,
                'type' => 'stock_expired_processed',
                'title' => 'Stock vencido procesado',
                'message' => 'Se descarto automaticamente stock vencido.',
                'channel' => 'app',
                'status' => 'pending',
                'created_at' => now(),
            ]);
        }
    }

    private function audit(StockItem $item, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => null,
            'action' => 'stock-item.expired-processed',
            'entity_name' => 'stock_items',
            'entity_id' => (string) $item->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => null,
            'user_agent' => 'artisan stock:process-expired',
        ]);
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

    private function skipped(): array
    {
        return [
            'processed' => false,
            'quantity' => 0.0,
            'estimated_loss' => 0.0,
        ];
    }
}
