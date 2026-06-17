<?php

namespace App\Services\Purchases;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Exceptions\Purchases\PurchaseException;
use App\Purchase;
use App\PurchaseItem;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\Purchases\PurchaseRepository;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    const EDITABLE_STATUSES = ['confirmed'];
    const VALID_STATUSES    = ['confirmed', 'cancelled'];

    private $repo;
    private $groupRepo;

    public function __construct(PurchaseRepository $repo, FamilyGroupRepository $groupRepo)
    {
        $this->repo      = $repo;
        $this->groupRepo = $groupRepo;
    }

    public function list(int $groupId, int $userId, array $filters)
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        return $this->repo->listForGroup($groupId, $filters);
    }

    public function show(int $groupId, int $purchaseId, int $userId): Purchase
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        return $this->repo->findForGroup($groupId, $purchaseId);
    }

    public function create(int $groupId, int $userId, array $data, string $ip, string $ua): Purchase
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        return DB::transaction(function () use ($groupId, $userId, $data, $ip, $ua) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $purchase = $this->repo->create(array_merge($data, [
                'family_group_id' => $groupId,
                'user_id'         => $userId,
                'status'          => 'confirmed',
            ]));

            $this->syncItems($purchase, $items);

            $purchase->refresh();
            $computed = $this->computeActualTotal($purchase);
            $this->repo->update($purchase, ['actual_total' => $computed]);
            $purchase->refresh();

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'purchase.create',
                'entity_name' => 'purchases',
                'entity_id'   => (string) $purchase->id,
                'old_values'  => null,
                'new_values'  => ['status' => $purchase->status, 'actual_total' => $purchase->actual_total],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $purchase->load(['items.product', 'items.unit', 'branch', 'paymentMethod']);
        });
    }

    public function update(int $groupId, int $purchaseId, int $userId, array $data, string $ip, string $ua): Purchase
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $purchase = $this->repo->findForGroup($groupId, $purchaseId);

        if (!in_array($purchase->status, self::EDITABLE_STATUSES)) {
            throw new PurchaseException('PURCHASE_NOT_EDITABLE', 'La compra no puede modificarse en su estado actual.', 409);
        }

        return DB::transaction(function () use ($purchase, $userId, $data, $ip, $ua) {
            $old = $purchase->only(['purchase_date', 'supermarket_branch_id', 'payment_method_id', 'estimated_total']);

            $allowed = ['purchase_date', 'supermarket_branch_id', 'payment_method_id', 'estimated_total', 'shopping_list_id'];
            $filtered = array_filter(
                array_intersect_key($data, array_flip($allowed)),
                function ($v) { return $v !== null; }
            );

            if (isset($data['items'])) {
                $this->syncItems($purchase, $data['items']);
                $purchase->refresh();
                $filtered['actual_total'] = $this->computeActualTotal($purchase);
            }

            $updated = $this->repo->update($purchase, $filtered);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'purchase.update',
                'entity_name' => 'purchases',
                'entity_id'   => (string) $purchase->id,
                'old_values'  => $old,
                'new_values'  => $filtered,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $updated->load(['items.product', 'items.unit', 'branch', 'paymentMethod']);
        });
    }

    public function cancel(int $groupId, int $purchaseId, int $userId, string $ip, string $ua): Purchase
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $purchase = $this->repo->findForGroup($groupId, $purchaseId);

        return DB::transaction(function () use ($purchase, $userId, $ip, $ua) {
            $old      = ['status' => $purchase->status, 'deleted_at' => null];
            $canceled = $this->repo->cancel($purchase);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'purchase.cancel',
                'entity_name' => 'purchases',
                'entity_id'   => (string) $purchase->id,
                'old_values'  => $old,
                'new_values'  => ['status' => 'cancelled', 'deleted_at' => (string) now()],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $canceled;
        });
    }

    private function syncItems(Purchase $purchase, array $items): void
    {
        PurchaseItem::where('purchase_id', $purchase->id)->delete();

        foreach ($items as $item) {
            $unitPrice  = isset($item['unit_price']) ? (float) $item['unit_price'] : null;
            $quantity   = (float) $item['quantity'];
            $totalPrice = ($unitPrice !== null) ? round($unitPrice * $quantity, 2) : null;

            PurchaseItem::create([
                'purchase_id'     => $purchase->id,
                'product_id'      => $item['product_id'],
                'quantity'        => $quantity,
                'unit_id'         => $item['unit_id'],
                'unit_price'      => $unitPrice,
                'total_price'     => $totalPrice,
                'expiration_date' => $item['expiration_date'] ?? null,
            ]);
        }
    }

    private function computeActualTotal(Purchase $purchase): ?float
    {
        $items = PurchaseItem::where('purchase_id', $purchase->id)->get();
        if ($items->isEmpty()) {
            return null;
        }
        $total = $items->sum(function ($i) {
            return (float) $i->total_price;
        });
        return $total > 0 ? round($total, 2) : null;
    }
}
