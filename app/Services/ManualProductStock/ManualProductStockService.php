<?php

namespace App\Services\ManualProductStock;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Product;
use App\ProductRequest;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ManualProductStock\ManualProductStockRepository;
use Illuminate\Support\Facades\DB;

class ManualProductStockService
{
    private $groups;
    private $repo;

    public function __construct(FamilyGroupRepository $groups, ManualProductStockRepository $repo)
    {
        $this->groups = $groups;
        $this->repo = $repo;
    }

    public function create(int $groupId, $actor, array $data, string $ip, ?string $userAgent): array
    {
        $this->groups->findOrFailForUser($groupId, $actor->id);
        $productInput = $this->prepareProductInput($data['product']);
        $stockInput = $this->prepareStockInput($data['stock'], $productInput);
        $movementInput = $data['movement'] ?? [];
        $this->validateRelations($groupId, $productInput, $stockInput);

        return DB::transaction(function () use ($groupId, $actor, $productInput, $stockInput, $movementInput, $ip, $userAgent) {
            $matchedExisting = false;
            $reusedPending = false;
            $createdProduct = false;
            $request = null;

            $product = $this->resolveProduct($groupId, $actor->id, $productInput, $matchedExisting, $reusedPending, $createdProduct);

            if (! $matchedExisting) {
                $request = $this->ensurePendingRequest($groupId, $actor->id, $product, $productInput);
            }

            list($stockItem, $status, $stockAction, $oldStock) = $this->createOrUpdateStock($groupId, $actor->id, $product, $stockInput);

            $movement = $this->repo->createMovement([
                'family_group_id' => $groupId,
                'stock_item_id' => $stockItem->id,
                'product_id' => $product->id,
                'movement_type' => $movementInput['movement_type'] ?? ($status === 201 ? 'manual_product_created' : 'manual_product_incremented'),
                'quantity' => $stockInput['quantity'],
                'unit_id' => $stockInput['unit_id'],
                'reason' => $movementInput['reason'] ?? 'Carga manual de producto',
                'related_purchase_id' => $movementInput['related_purchase_id'] ?? null,
                'created_by' => $actor->id,
                'created_at' => now(),
            ]);

            if ($createdProduct) {
                $this->audit($actor->id, 'manual-product.created', 'products', $product->id, null, $this->productPayload($product), $ip, $userAgent);
            }

            if ($request) {
                $this->audit($actor->id, 'manual-product.request-linked', 'product_requests', $request->id, null, $this->requestPayload($request), $ip, $userAgent);
            }

            $this->audit($actor->id, $stockAction, 'stock_items', $stockItem->id, $oldStock, $this->stockPayload($stockItem), $ip, $userAgent);

            return [
                'product' => $product->fresh(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes']),
                'stock_item' => $stockItem->fresh(['product', 'location', 'unit']),
                'stock_movement' => $movement,
                'review_status' => $matchedExisting ? 'approved' : 'pending_review',
                'matched_existing_product' => $matchedExisting,
                'reused_pending_product' => $reusedPending,
                'message' => $matchedExisting
                    ? 'Producto encontrado. Actualizamos tu stock.'
                    : 'Producto cargado en tu stock. Quedo pendiente de revision del catalogo.',
                'status' => $status,
            ];
        });
    }

    private function resolveProduct(int $groupId, int $userId, array $data, bool &$matchedExisting, bool &$reusedPending, bool &$createdProduct): Product
    {
        if (! empty($data['barcode'])) {
            $active = $this->repo->findActiveProductByBarcode($data['barcode']);
            if ($active) {
                $matchedExisting = true;
                return $active;
            }

            $pending = $this->repo->findPendingProductByBarcode($groupId, $data['barcode']);
            if ($pending) {
                $reusedPending = true;
                return $pending;
            }

            if ($this->repo->barcodeExists($data['barcode'])) {
                throw new IngredientException('PRODUCT_BARCODE_ALREADY_EXISTS', 'Este codigo ya esta asociado a otro producto en revision.', 409);
            }
        }

        $duplicate = $this->repo->findPendingDuplicate($groupId, $data['name'], $data['barcode'] ?? null, $data['presentation'] ?? null);
        if ($duplicate) {
            $reusedPending = true;
            return $duplicate;
        }

        $createdProduct = true;
        $product = $this->repo->createProduct($this->productData($groupId, $userId, $data));

        if (! empty($data['barcode'])) {
            $this->repo->addBarcode($product, $data['barcode']);
        }

        return $product->fresh(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes']);
    }

    private function createOrUpdateStock(int $groupId, int $userId, Product $product, array $data): array
    {
        $duplicate = \App\StockItem::resolveActiveLot(
            $groupId,
            (int) $product->id,
            isset($data['stock_location_id']) ? (int) $data['stock_location_id'] : null,
            (int) $data['unit_id'],
            $data['expiration_date'] ?? null
        );

        if ($duplicate) {
            $old = $this->stockPayload($duplicate);
            $updated = $this->repo->updateStockItem($duplicate, array_merge($data, [
                'quantity' => (float) $duplicate->quantity + (float) $data['quantity'],
            ]));

            return [$updated, 200, 'stock-item.updated', $old];
        }

        $created = $this->repo->createStockItem(array_merge($data, [
            'family_group_id' => $groupId,
            'product_id' => $product->id,
            'status' => 'active',
        ]));

        return [$created, 201, 'stock-item.created', null];
    }

    private function ensurePendingRequest(int $groupId, int $userId, Product $product, array $data)
    {
        $existing = $this->repo->findPendingRequest($groupId, $userId, $product);
        if ($existing) {
            return null;
        }

        return $this->repo->createRequest([
            'requested_by_user_id' => $userId,
            'family_group_id' => $groupId,
            'name' => $product->name,
            'normalized_name' => $this->normalize($product->name),
            'brand' => $data['brand'] ?? null,
            'presentation' => $data['presentation'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'unit_id' => $data['unit_id'],
            'comment' => 'Creado automaticamente desde carga manual de stock.',
            'source' => ! empty($data['barcode']) ? 'barcode' : 'stock',
            'status' => ProductRequest::STATUS_PENDING,
            'product_id' => $product->id,
        ]);
    }

    private function validateRelations(int $groupId, array $product, array $stock): void
    {
        foreach (array_filter([$product['unit_id'], $product['package_unit_id'] ?? null, $stock['unit_id']]) as $unitId) {
            if (! $this->repo->activeUnitExists((int) $unitId)) {
                throw new IngredientException('STOCK_UNIT_INVALID', 'La unidad indicada no existe o no esta activa.', 422);
            }
        }

        if (! empty($stock['stock_location_id']) && ! $this->repo->activeLocationInGroupExists($groupId, (int) $stock['stock_location_id'])) {
            throw new IngredientException('STOCK_LOCATION_NOT_FOUND', 'Ubicacion de stock no encontrada.', 404);
        }
    }

    private function prepareProductInput(array $data): array
    {
        $name = preg_replace('/\s+/', ' ', trim($data['name']));

        return [
            'name' => $name,
            'brand' => $this->emptyToNull($data['brand'] ?? null),
            'presentation' => $this->emptyToNull($data['presentation'] ?? null),
            'barcode' => $this->emptyToNull($data['barcode'] ?? null),
            'unit_id' => (int) ($data['unit_id'] ?? $data['default_unit_id']),
            'net_quantity' => $data['net_quantity'] ?? null,
            'package_unit_id' => $data['package_unit_id'] ?? null,
            'ingredient_id' => $data['ingredient_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
        ];
    }

    private function prepareStockInput(array $data, array $product): array
    {
        $prepared = [
            'stock_location_id' => $data['stock_location_id'] ?? null,
            'quantity' => $data['quantity'],
            'unit_id' => $data['unit_id'] ?? $product['unit_id'],
            'expiration_date' => $data['expiration_date'] ?? null,
            'estimated_purchase_price' => $data['purchase_price'] ?? null,
        ];

        return array_filter($prepared, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function productData(int $groupId, int $userId, array $data): array
    {
        $description = trim(implode(' | ', array_filter([
            $data['brand'] ? 'Marca: '.$data['brand'] : null,
            $data['presentation'] ? 'Presentacion: '.$data['presentation'] : null,
        ])));

        $unique = $data['barcode'] ? $data['barcode'] : 'g'.$groupId.'-u'.$userId.'-'.substr(md5($data['name'].microtime(true)), 0, 8);

        return [
            'name' => $data['name'],
            'normalized_name' => $this->normalize($data['name']).' manual '.$unique,
            'brand_id' => 0,
            'category_id' => $data['category_id'] ?? null,
            'ingredient_id' => $data['ingredient_id'] ?? null,
            'default_unit_id' => $data['unit_id'],
            'net_quantity' => $data['net_quantity'] ?? null,
            'package_unit_id' => $data['package_unit_id'] ?? null,
            'description' => $description ?: null,
            'is_verified' => false,
            'is_active' => true,
            'status' => 'pending_review',
            'origin' => 'user_created',
            'created_by_user_id' => $userId,
            'family_group_id' => $groupId,
            'nombre' => $data['name'],
            'codigo' => $data['barcode'] ?? 'manual-'.$unique,
            'img' => '',
            'habilitado' => 1,
            'supply_id' => 0,
        ];
    }

    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'status' => $product->status,
            'origin' => $product->origin,
            'family_group_id' => $product->family_group_id,
        ];
    }

    private function stockPayload($item): array
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
        ];
    }

    private function requestPayload(ProductRequest $request): array
    {
        return [
            'id' => $request->id,
            'product_id' => $request->product_id,
            'status' => $request->status,
            'source' => $request->source,
        ];
    }

    private function audit($actorId, $action, $entityName, $entityId, $old, $new, $ip, $userAgent): void
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => $entityName,
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    private function normalize($value)
    {
        // mb_strtolower: strtolower() corrompe el byte inicial de caracteres UTF-8
        // acentuados (0xC3 -> 0xE3) y PostgreSQL rechaza el texto resultante.
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $value)), 'UTF-8');
    }

    private function emptyToNull($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
