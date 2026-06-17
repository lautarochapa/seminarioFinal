<?php

namespace App\Services\Purchases;

use App\AuditLog;
use App\Exceptions\Purchases\PurchaseException;
use App\Product;
use App\Purchase;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\Purchases\PurchaseItemRepository;
use App\Repositories\Purchases\PurchaseRepository;
use Illuminate\Support\Facades\DB;

class PurchaseItemService
{
    private $itemRepo;
    private $purchaseRepo;
    private $groupRepo;

    public function __construct(
        PurchaseItemRepository $itemRepo,
        PurchaseRepository $purchaseRepo,
        FamilyGroupRepository $groupRepo
    ) {
        $this->itemRepo     = $itemRepo;
        $this->purchaseRepo = $purchaseRepo;
        $this->groupRepo    = $groupRepo;
    }

    public function list(int $groupId, int $purchaseId, int $userId)
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $this->purchaseRepo->findForGroup($groupId, $purchaseId);
        return $this->itemRepo->listForPurchase($purchaseId);
    }

    public function create(int $groupId, int $purchaseId, int $userId, array $data, string $ip, string $ua)
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $purchase = $this->purchaseRepo->findForGroup($groupId, $purchaseId);
        $this->requireEditable($purchase);

        $this->requireActiveProduct((int) $data['product_id']);

        return DB::transaction(function () use ($purchase, $userId, $data, $ip, $ua) {
            $unitPrice  = isset($data['unit_price']) ? (float) $data['unit_price'] : null;
            $quantity   = (float) $data['quantity'];
            $totalPrice = ($unitPrice !== null) ? round($unitPrice * $quantity, 2) : null;

            $item = $this->itemRepo->create([
                'purchase_id'     => $purchase->id,
                'product_id'      => $data['product_id'],
                'quantity'        => $quantity,
                'unit_id'         => $data['unit_id'],
                'unit_price'      => $unitPrice,
                'total_price'     => $totalPrice,
                'expiration_date' => $data['expiration_date'] ?? null,
            ]);

            $newTotal = $this->itemRepo->recalculateTotal($purchase->id);
            $this->purchaseRepo->update($purchase, ['actual_total' => $newTotal]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'purchase_item.create',
                'entity_name' => 'purchase_items',
                'entity_id'   => (string) $item->id,
                'old_values'  => null,
                'new_values'  => ['purchase_id' => $purchase->id, 'product_id' => $item->product_id, 'total_price' => $item->total_price],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $item->load(['product', 'unit']);
        });
    }

    public function update(int $groupId, int $purchaseId, int $itemId, int $userId, array $data, string $ip, string $ua)
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $purchase = $this->purchaseRepo->findForGroup($groupId, $purchaseId);
        $this->requireEditable($purchase);
        $item = $this->itemRepo->findForPurchase($purchaseId, $itemId);

        if (isset($data['product_id'])) {
            $this->requireActiveProduct((int) $data['product_id']);
        }

        return DB::transaction(function () use ($purchase, $item, $userId, $data, $ip, $ua) {
            $old = $item->only(['quantity', 'unit_id', 'unit_price', 'total_price', 'expiration_date']);

            $quantity  = isset($data['quantity'])   ? (float) $data['quantity']   : (float) $item->quantity;
            $unitPrice = array_key_exists('unit_price', $data)
                ? ($data['unit_price'] !== null ? (float) $data['unit_price'] : null)
                : (float) $item->unit_price;

            $totalPrice = ($unitPrice !== null) ? round($unitPrice * $quantity, 2) : null;

            $allowed = ['product_id', 'quantity', 'unit_id', 'unit_price', 'expiration_date'];
            $filtered = array_intersect_key($data, array_flip($allowed));
            $filtered['quantity']    = $quantity;
            $filtered['unit_price']  = $unitPrice;
            $filtered['total_price'] = $totalPrice;

            $updated  = $this->itemRepo->update($item, $filtered);
            $newTotal = $this->itemRepo->recalculateTotal($purchase->id);
            $this->purchaseRepo->update($purchase, ['actual_total' => $newTotal]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'purchase_item.update',
                'entity_name' => 'purchase_items',
                'entity_id'   => (string) $item->id,
                'old_values'  => $old,
                'new_values'  => $filtered,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $updated;
        });
    }

    public function delete(int $groupId, int $purchaseId, int $itemId, int $userId, string $ip, string $ua): void
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $purchase = $this->purchaseRepo->findForGroup($groupId, $purchaseId);
        $this->requireEditable($purchase);
        $item = $this->itemRepo->findForPurchase($purchaseId, $itemId);

        DB::transaction(function () use ($purchase, $item, $userId, $ip, $ua) {
            $old = $item->only(['product_id', 'quantity', 'unit_price', 'total_price']);

            $this->itemRepo->delete($item);

            $newTotal = $this->itemRepo->recalculateTotal($purchase->id);
            $this->purchaseRepo->update($purchase, ['actual_total' => $newTotal]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'purchase_item.delete',
                'entity_name' => 'purchase_items',
                'entity_id'   => (string) $item->id,
                'old_values'  => $old,
                'new_values'  => null,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);
        });
    }

    private function requireEditable(Purchase $purchase): void
    {
        if (!in_array($purchase->status, PurchaseService::EDITABLE_STATUSES)) {
            throw new PurchaseException('PURCHASE_NOT_EDITABLE', 'La compra no puede modificarse en su estado actual.', 409);
        }
    }

    private function requireActiveProduct(int $productId): void
    {
        $product = Product::find($productId);
        if (!$product || $product->status !== 'active') {
            throw new PurchaseException('PURCHASE_ITEM_PRODUCT_INACTIVE', 'El producto no existe o no esta activo.', 422);
        }
    }
}
