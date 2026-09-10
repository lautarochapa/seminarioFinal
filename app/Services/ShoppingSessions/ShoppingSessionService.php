<?php

namespace App\Services\ShoppingSessions;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Purchase;
use App\PurchaseItem;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ShoppingLists\ShoppingListRepository;
use App\Repositories\ShoppingSessions\ShoppingSessionRepository;
use App\ShoppingList;
use App\ShoppingSession;
use App\ShoppingSessionScan;
use App\StockItem;
use App\StockLocation;
use App\StockMovement;
use App\SupermarketBranch;
use App\User;
use Illuminate\Support\Facades\DB;

class ShoppingSessionService
{
    private $groups;
    private $lists;
    private $sessions;

    public function __construct(FamilyGroupRepository $groups, ShoppingListRepository $lists, ShoppingSessionRepository $sessions)
    {
        $this->groups = $groups;
        $this->lists = $lists;
        $this->sessions = $sessions;
    }

    public function start(User $user, int $groupId, int $listId, string $ip, string $ua): ShoppingSession
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);

        if ($this->sessions->activeForListAndUser($list->id, $user->id)) {
            throw new FamilyGroupException('SHOPPING_SESSION_ALREADY_ACTIVE', 'Ya existe una sesion activa para esta lista.', 409);
        }

        if ($list->items()->count() === 0) {
            // Mismo codigo/estado que ShoppingListService::start para consistencia.
            throw new FamilyGroupException('SHOPPING_LIST_EMPTY', 'La lista no tiene articulos para comprar.', 422);
        }

        if ($list->status !== ShoppingList::STATUS_IN_PROGRESS) {
            if (!$list->canTransitionTo(ShoppingList::STATUS_IN_PROGRESS)) {
                throw new FamilyGroupException(
                    'SHOPPING_LIST_INVALID_STATUS_TRANSITION',
                    'La lista debe estar "Lista para comprar" para comenzar la compra.',
                    409
                );
            }
            $list->status = ShoppingList::STATUS_IN_PROGRESS;
            $list->save();
        }

        $session = $this->sessions->create([
            'shopping_list_id' => $list->id,
            'family_group_id' => $groupId,
            'user_id' => $user->id,
            'started_at' => now(),
            'status' => 'active',
        ]);

        $this->audit($user->id, 'shopping_session.started', $session->id, null, $this->payload($session), $ip, $ua);

        return $session;
    }

    public function update(User $user, int $groupId, int $sessionId, array $data, string $ip, string $ua): ShoppingSession
    {
        $this->assertMember($user, $groupId);
        $session = $this->findSession($groupId, $sessionId);
        $this->assertActive($session);

        if (isset($data['supermarket_branch_id']) && !$this->activeBranchExists((int) $data['supermarket_branch_id'])) {
            throw new FamilyGroupException('SUPERMARKET_BRANCH_NOT_FOUND', 'La sucursal no existe o no esta activa.', 422);
        }

        $old = $this->payload($session);
        $session->fill(array_intersect_key($data, array_flip(['supermarket_branch_id'])));
        $session->save();
        $session = $session->fresh(['shoppingList', 'branch']);
        $new = $this->payload($session);

        if ($old !== $new) {
            $this->audit($user->id, 'shopping_session.updated', $session->id, $old, $new, $ip, $ua);
        }

        return $session;
    }

    public function scan(User $user, int $groupId, int $sessionId, array $data, string $ip, string $ua): ShoppingSessionScan
    {
        $this->assertMember($user, $groupId);
        $session = $this->findSession($groupId, $sessionId);
        $this->assertActive($session);
        $product = $this->sessions->productByBarcode($data['barcode']);

        if (!$product) {
            throw new FamilyGroupException('SHOPPING_SCAN_PRODUCT_NOT_FOUND', 'Producto no encontrado para el codigo escaneado.', 404);
        }

        if ($this->sessions->scanExists($session->id, $product->id)) {
            throw new FamilyGroupException('SHOPPING_SCAN_ALREADY_REGISTERED', 'El producto ya fue escaneado en esta sesion.', 409);
        }

        $item = $session->shoppingList->items->first(function ($listItem) use ($product) {
            return (int) $listItem->product_id === (int) $product->id;
        });

        if (!$item) {
            throw new FamilyGroupException('SHOPPING_SCAN_ITEM_NOT_FOUND', 'El producto no pertenece a la lista.', 404);
        }

        return DB::transaction(function () use ($user, $session, $item, $product, $data, $ip, $ua) {
            $scan = $this->sessions->createScan([
                'shopping_session_id' => $session->id,
                'barcode' => $data['barcode'],
                'product_id' => $product->id,
                'shopping_list_item_id' => $item->id,
                'quantity' => $data['quantity'] ?? $item->quantity,
                'price' => $data['price'] ?? null,
                'scan_result' => 'matched',
            ]);

            $item->status = 'purchased';
            if (isset($data['price'])) {
                $item->actual_price = $data['price'];
            }
            $item->save();

            $this->audit($user->id, 'shopping_session.scan_registered', $session->id, null, [
                'scan_id' => $scan->id,
                'product_id' => $product->id,
                'shopping_list_item_id' => $item->id,
            ], $ip, $ua);

            return $scan;
        });
    }

    public function finish(User $user, int $groupId, int $sessionId, array $data, string $ip, string $ua): array
    {
        $this->assertMember($user, $groupId);
        $session = $this->findSession($groupId, $sessionId);

        if ($session->status === 'finished') {
            throw new FamilyGroupException('SHOPPING_SESSION_ALREADY_FINISHED', 'La sesion ya fue finalizada.', 409);
        }

        $this->assertActive($session);

        if (isset($data['stock_location_id']) && !$this->activeStockLocationExists($groupId, (int) $data['stock_location_id'])) {
            throw new FamilyGroupException('STOCK_LOCATION_NOT_FOUND', 'La ubicacion de stock no existe para este grupo.', 422);
        }

        return DB::transaction(function () use ($user, $groupId, $session, $data, $ip, $ua) {
            $old = $this->payload($session);
            $scans = $session->scans()->where('scan_result', 'matched')->get();

            $stockLocationId = $data['stock_location_id'] ?? $this->defaultStockLocationId($groupId);

            $stockCreated = 0;
            $stockUpdated = 0;
            $stockSkipped = 0;
            $warnings = [];
            $purchasedTotal = 0.0;

            $purchase = Purchase::create([
                'family_group_id' => $groupId,
                'shopping_list_id' => $session->shopping_list_id,
                'supermarket_branch_id' => $session->supermarket_branch_id,
                'user_id' => $user->id,
                'purchase_date' => now()->toDateString(),
                'estimated_total' => 0,
                'actual_total' => 0,
                'status' => 'confirmed',
            ]);

            $actualTotal = 0.0;

            foreach ($scans as $scan) {
                $item = $scan->shoppingListItem;
                $quantity = (float) ($scan->quantity ?: ($item->quantity ?? 0));

                if (!$item || !$scan->product_id || $quantity <= 0) {
                    $stockSkipped++;
                    $warnings[] = [
                        'shopping_list_item_id' => $item->id ?? null,
                        'reason' => !$scan->product_id
                            ? 'ITEM_WITHOUT_PRODUCT'
                            : ($quantity <= 0 ? 'ZERO_QUANTITY' : 'ITEM_NOT_FOUND'),
                    ];
                    continue;
                }

                if ($scan->price !== null && $scan->quantity !== null) {
                    $actualTotal += (float) $scan->price * (float) $scan->quantity;
                }
                $purchasedTotal += (float) ($item->estimated_price ?? 0) * $quantity;

                $existingStock = StockItem::where('family_group_id', $groupId)
                    ->where('product_id', $scan->product_id)
                    ->where('unit_id', $item->unit_id)
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($stockLocationId) {
                        $stockLocationId === null ? $q->whereNull('stock_location_id') : $q->where('stock_location_id', $stockLocationId);
                    })
                    ->whereNull('expiration_date')
                    ->first();

                if ($existingStock) {
                    $existingStock->quantity = (float) $existingStock->quantity + $quantity;
                    if ($scan->price !== null) {
                        $existingStock->estimated_purchase_price = $scan->price;
                    }
                    $existingStock->save();
                    $stock = $existingStock;
                    $stockUpdated++;
                } else {
                    $stock = StockItem::create([
                        'family_group_id' => $groupId,
                        'product_id' => $scan->product_id,
                        'stock_location_id' => $stockLocationId,
                        'quantity' => $quantity,
                        'unit_id' => $item->unit_id,
                        'purchase_date' => now()->toDateString(),
                        'estimated_purchase_price' => $scan->price,
                        'status' => 'active',
                    ]);
                    $stockCreated++;
                }

                StockMovement::create([
                    'family_group_id' => $groupId,
                    'stock_item_id' => $stock->id,
                    'product_id' => $scan->product_id,
                    'movement_type' => 'entry',
                    'quantity' => $quantity,
                    'unit_id' => $item->unit_id,
                    'reason' => 'shopping_session',
                    'related_purchase_id' => $purchase->id,
                    'created_by' => $user->id,
                ]);

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $scan->product_id,
                    'quantity' => $quantity,
                    'unit_id' => $item->unit_id,
                    'unit_price' => $scan->price,
                    'total_price' => $scan->price !== null ? (float) $scan->price * $quantity : null,
                    'created_stock_item_id' => $stock->id,
                ]);
            }

            $purchase->estimated_total = $purchasedTotal;
            $purchase->actual_total = $actualTotal;
            $purchase->save();

            $session->status = 'finished';
            $session->finished_at = now();
            $session->save();

            $list = $session->shoppingList;
            if ($list->canTransitionTo(ShoppingList::STATUS_COMPLETED)) {
                $list->status = ShoppingList::STATUS_COMPLETED;
                $list->save();
            }

            $session = $session->fresh(['shoppingList', 'branch']);
            $this->audit($user->id, 'shopping_session.finished', $session->id, $old, $this->payload($session), $ip, $ua);

            return [
                'session' => $session,
                'summary' => [
                    'purchase_id' => $purchase->id,
                    'stock_created_count' => $stockCreated,
                    'stock_updated_count' => $stockUpdated,
                    'stock_skipped_count' => $stockSkipped,
                    'stock_warnings' => $warnings,
                ],
            ];
        });
    }

    private function activeStockLocationExists(int $groupId, int $locationId): bool
    {
        return StockLocation::where('id', $locationId)
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
    }

    private function defaultStockLocationId(int $groupId): ?int
    {
        $locations = StockLocation::where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->pluck('id');

        return $locations->count() === 1 ? (int) $locations->first() : null;
    }

    private function assertMember(User $user, int $groupId): void
    {
        $this->groups->findOrFailForUser($groupId, $user->id);
    }

    private function findList(int $groupId, int $listId): ShoppingList
    {
        $list = $this->lists->findInGroup($groupId, $listId);
        if (!$list) {
            throw new FamilyGroupException('SHOPPING_LIST_NOT_FOUND', 'La lista de compras no existe.', 404);
        }

        return $list;
    }

    private function findSession(int $groupId, int $sessionId): ShoppingSession
    {
        $session = $this->sessions->findInGroup($groupId, $sessionId);
        if (!$session) {
            throw new FamilyGroupException('SHOPPING_SESSION_NOT_FOUND', 'La sesion de compra no existe.', 404);
        }

        return $session;
    }

    private function assertActive(ShoppingSession $session): void
    {
        if ($session->status !== 'active') {
            throw new FamilyGroupException('SHOPPING_SESSION_NOT_ACTIVE', 'La sesion no esta activa.', 409);
        }
    }

    private function activeBranchExists(int $branchId): bool
    {
        return SupermarketBranch::where('id', $branchId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
    }

    private function payload(ShoppingSession $session): array
    {
        return [
            'shopping_list_id' => $session->shopping_list_id,
            'family_group_id' => $session->family_group_id,
            'user_id' => $session->user_id,
            'supermarket_branch_id' => $session->supermarket_branch_id,
            'started_at' => optional($session->started_at)->toIso8601String(),
            'finished_at' => optional($session->finished_at)->toIso8601String(),
            'status' => $session->status,
        ];
    }

    private function audit(int $userId, string $action, int $sessionId, ?array $old, array $new, string $ip, string $ua): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_name' => 'shopping_sessions',
            'entity_id' => (string) $sessionId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }
}
