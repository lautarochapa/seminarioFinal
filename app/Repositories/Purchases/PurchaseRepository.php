<?php

namespace App\Repositories\Purchases;

use App\Exceptions\Purchases\PurchaseException;
use App\Purchase;

class PurchaseRepository
{
    public function listForGroup(int $groupId, array $filters)
    {
        $query = Purchase::where('family_group_id', $groupId)
            ->whereNull('deleted_at');

        if (!empty($filters['date_from'])) {
            $query->whereDate('purchase_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('purchase_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['supermarket_branch_id'])) {
            $query->where('supermarket_branch_id', $filters['supermarket_branch_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        return $query->orderBy('purchase_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function findForGroup(int $groupId, int $purchaseId): Purchase
    {
        $purchase = Purchase::where('family_group_id', $groupId)
            ->where('id', $purchaseId)
            ->whereNull('deleted_at')
            ->with(['items.product', 'items.unit', 'branch', 'paymentMethod'])
            ->first();

        if (!$purchase) {
            throw new PurchaseException('PURCHASE_NOT_FOUND', 'La compra no existe o no pertenece al grupo.', 404);
        }

        return $purchase;
    }

    public function create(array $data): Purchase
    {
        return Purchase::create($data);
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        $purchase->update($data);
        return $purchase->fresh();
    }

    public function cancel(Purchase $purchase): Purchase
    {
        $purchase->update(['status' => 'cancelled']);
        $purchase->delete();
        return Purchase::withTrashed()->find($purchase->id);
    }
}
