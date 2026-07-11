<?php

namespace App\Services\Purchases;

use App\AuditLog;
use App\Budget;
use App\Exceptions\Purchases\PurchaseException;
use App\Purchase;
use App\PurchaseItem;
use App\Repositories\Budgets\BudgetAlertRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\HouseholdStock\HouseholdStockRepository;
use App\Repositories\Purchases\PurchaseRepository;
use App\Repositories\StockMovements\StockMovementRepository;
use App\StockItem;
use Illuminate\Support\Facades\DB;

class PurchaseConfirmationService
{
    const STATUS_CONFIRMED    = 'confirmed';
    const STATUS_STOCK_ADDED  = 'stock_added';
    const STATUS_CANCELLED    = 'cancelled';

    private $purchaseRepo;
    private $groupRepo;
    private $stockRepo;
    private $movementRepo;
    private $budgetAlerts;

    public function __construct(
        PurchaseRepository $purchaseRepo,
        FamilyGroupRepository $groupRepo,
        HouseholdStockRepository $stockRepo,
        StockMovementRepository $movementRepo,
        BudgetAlertRepository $budgetAlerts
    ) {
        $this->purchaseRepo = $purchaseRepo;
        $this->groupRepo    = $groupRepo;
        $this->stockRepo    = $stockRepo;
        $this->movementRepo = $movementRepo;
        $this->budgetAlerts = $budgetAlerts;
    }

    public function confirm(int $groupId, int $purchaseId, int $userId, string $ip, string $ua): Purchase
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $purchase = $this->purchaseRepo->findForGroup($groupId, $purchaseId);

        if ($purchase->status === self::STATUS_CANCELLED) {
            throw new PurchaseException('PURCHASE_CANCELLED', 'No se puede confirmar una compra cancelada.', 409);
        }

        if ($purchase->status === self::STATUS_STOCK_ADDED) {
            throw new PurchaseException('PURCHASE_ALREADY_CONFIRMED', 'La compra ya fue confirmada y tiene stock cargado.', 409);
        }

        $items = PurchaseItem::where('purchase_id', $purchaseId)->get();
        if ($items->isEmpty()) {
            throw new PurchaseException('PURCHASE_HAS_NO_ITEMS', 'La compra no tiene items para confirmar.', 422);
        }

        return DB::transaction(function () use ($purchase, $items, $userId, $ip, $ua) {
            $total = $items->sum(function ($i) { return (float) $i->total_price; });
            $actual = $total > 0 ? round($total, 2) : null;

            $this->purchaseRepo->update($purchase, ['actual_total' => $actual]);
            $purchase->refresh();
            $this->createBudgetAlertIfNeeded($purchase);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'purchase.confirm',
                'entity_name' => 'purchases',
                'entity_id'   => (string) $purchase->id,
                'old_values'  => ['actual_total' => null],
                'new_values'  => ['actual_total' => $actual, 'items_count' => $items->count()],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $purchase->load(['items.product', 'items.unit', 'branch', 'paymentMethod']);
        });
    }

    public function addToStock(int $groupId, int $purchaseId, int $userId, string $ip, string $ua): Purchase
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        return DB::transaction(function () use ($groupId, $purchaseId, $userId, $ip, $ua) {
            $purchase = Purchase::where('family_group_id', $groupId)
                ->where('id', $purchaseId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$purchase) {
                throw new PurchaseException('PURCHASE_NOT_FOUND', 'La compra no existe o no pertenece al grupo.', 404);
            }

            if ($purchase->status === self::STATUS_CANCELLED) {
                throw new PurchaseException('PURCHASE_CANCELLED', 'No se puede cargar stock de una compra cancelada.', 409);
            }

            if ($purchase->status === self::STATUS_STOCK_ADDED) {
                throw new PurchaseException('PURCHASE_STOCK_ALREADY_ADDED', 'El stock de esta compra ya fue cargado.', 409);
            }

            $items = PurchaseItem::where('purchase_id', $purchaseId)
                ->whereNull('created_stock_item_id')
                ->with(['product'])
                ->get();

            if ($items->isEmpty()) {
                throw new PurchaseException('PURCHASE_HAS_NO_ITEMS', 'La compra no tiene items pendientes de cargar al stock.', 422);
            }

            foreach ($items as $item) {
                if (!$item->product || $item->product->status !== 'active') {
                    throw new PurchaseException('PURCHASE_ITEM_PRODUCT_INACTIVE', 'El producto del item ID ' . $item->id . ' no esta activo.', 422);
                }

                $stockItem = $this->resolveStockItem($groupId, $item, $purchase);

                $this->movementRepo->create([
                    'family_group_id'     => $groupId,
                    'stock_item_id'       => $stockItem->id,
                    'product_id'          => $item->product_id,
                    'movement_type'       => 'purchase_entry',
                    'quantity'            => (float) $item->quantity,
                    'unit_id'             => $item->unit_id,
                    'reason'              => 'Carga desde compra #' . $purchase->id,
                    'related_purchase_id' => $purchase->id,
                    'created_by'          => $userId,
                    'created_at'          => now(),
                ]);

                $item->update(['created_stock_item_id' => $stockItem->id]);
            }

            $purchase->update(['status' => self::STATUS_STOCK_ADDED]);
            $purchase->refresh();

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'purchase.stock_added',
                'entity_name' => 'purchases',
                'entity_id'   => (string) $purchase->id,
                'old_values'  => ['status' => self::STATUS_CONFIRMED],
                'new_values'  => ['status' => self::STATUS_STOCK_ADDED, 'items_processed' => $items->count()],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $purchase->load(['items.product', 'items.unit', 'branch', 'paymentMethod']);
        });
    }

    private function resolveStockItem(int $groupId, PurchaseItem $item, Purchase $purchase): StockItem
    {
        $existing = StockItem::where('family_group_id', $groupId)
            ->where('product_id', $item->product_id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($existing) {
            $existing->quantity = round((float) $existing->quantity + (float) $item->quantity, 4);
            if ($item->unit_price !== null) {
                $existing->estimated_purchase_price = (float) $item->unit_price;
            }
            $existing->save();
            return $existing;
        }

        return StockItem::create([
            'family_group_id'          => $groupId,
            'product_id'               => $item->product_id,
            'quantity'                 => (float) $item->quantity,
            'unit_id'                  => $item->unit_id,
            'expiration_date'          => $item->expiration_date,
            'purchase_date'            => $purchase->purchase_date,
            'estimated_purchase_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
            'status'                   => 'active',
        ]);
    }

    private function createBudgetAlertIfNeeded(Purchase $purchase): void
    {
        if ($purchase->actual_total === null || ! $purchase->purchase_date) {
            return;
        }

        $budget = Budget::where('family_group_id', $purchase->family_group_id)
            ->where('year', (int) date('Y', strtotime($purchase->purchase_date)))
            ->where('month', (int) date('n', strtotime($purchase->purchase_date)))
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $budget || (float) $budget->total_amount <= 0) {
            return;
        }

        $spent = round((float) Purchase::where('family_group_id', $purchase->family_group_id)
            ->whereYear('purchase_date', $budget->year)
            ->whereMonth('purchase_date', $budget->month)
            ->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_STOCK_ADDED])
            ->whereNull('deleted_at')
            ->sum('actual_total'), 2);

        $percent = round(($spent / (float) $budget->total_amount) * 100, 2);
        $type = null;
        $severity = null;

        if ($spent >= (float) $budget->total_amount) {
            $type = 'limit_exceeded';
            $severity = 'critical';
        } elseif ($percent >= 80) {
            $type = 'near_limit';
            $severity = 'warning';
        }

        if (! $type || $this->budgetAlerts->existsUnreadForType($budget->id, $type)) {
            return;
        }

        $this->budgetAlerts->create([
            'budget_id' => $budget->id,
            'alert_type' => $type,
            'message' => 'El presupuesto alcanzo el '.$percent.'% de consumo.',
            'severity' => $severity,
            'status' => 'unread',
            'created_at' => now(),
        ]);
    }
}
