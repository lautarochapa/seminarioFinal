<?php

namespace App\Services\ProductRequests;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\FamilyGroupMember;
use App\ProductBarcode;
use App\ProductRequest;
use App\Product;
use App\Repositories\ProductRequests\ProductRequestRepository;
use App\Services\Products\ProductService;
use App\UnitMeasure;
use Illuminate\Support\Facades\DB;

class ProductRequestService
{
    private $requests;
    private $products;

    public function __construct(ProductRequestRepository $requests, ProductService $products)
    {
        $this->requests = $requests;
        $this->products = $products;
    }

    public function list(array $filters, $actor)
    {
        return $this->requests->paginate($filters, $actor);
    }

    public function show($id, $actor)
    {
        return $this->requests->findVisibleOrFail($id, $actor);
    }

    public function create($actor, array $data, $ip, $userAgent)
    {
        $data = $this->prepareCreateData($actor->id, $data);
        $this->assertFamilyGroupAccess($actor->id, $data['family_group_id'] ?? null);

        if ($this->requests->pendingDuplicateExists($actor->id, $data['normalized_name'], $data['barcode'] ?? null)) {
            throw new IngredientException('PRODUCT_REQUEST_ALREADY_PENDING', 'Ya existe una solicitud pendiente para este producto.', 409);
        }

        if (! empty($data['barcode']) && ProductBarcode::where('barcode', $data['barcode'])->where('status', 'active')->exists()) {
            throw new IngredientException('PRODUCT_BARCODE_ALREADY_EXISTS', 'Ya existe un producto activo con ese codigo de barras.', 409);
        }

        return DB::transaction(function () use ($actor, $data, $ip, $userAgent) {
            $request = $this->requests->create($data);
            $this->audit($actor->id, 'product_request_created', $request->id, null, $this->snapshot($request), $ip, $userAgent);

            return $request;
        });
    }

    public function approve($actor, $id, array $data, $ip, $userAgent)
    {
        if (! $actor->hasPermission('catalog.manage')) {
            throw new IngredientException('PERMISSION_DENIED', 'Sin permiso para esta accion.', 403);
        }

        return DB::transaction(function () use ($actor, $id, $data, $ip, $userAgent) {
            $request = $this->requests->findForReviewOrFail($id);
            $this->assertPending($request);

            if ($request->product && $request->product->status === 'pending_review') {
                $product = $this->approvePendingProduct($request->product, $data);
            } else {
                $productData = $this->prepareProductData($request, $data);
                $product = $this->products->create($actor->id, $productData, $ip, $userAgent);
            }

            $old = $this->snapshot($request);
            $updated = $this->requests->update($request, [
                'status' => ProductRequest::STATUS_APPROVED,
                'product_id' => $product->id,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ]);

            $this->audit($actor->id, 'product_request_approved', $updated->id, $old, $this->snapshot($updated), $ip, $userAgent);
            $this->audit($actor->id, 'product_created_from_request', $product->id, null, [
                'product_id' => $product->id,
                'product_request_id' => $updated->id,
            ], $ip, $userAgent);

            return $updated;
        });
    }

    public function reject($actor, $id, array $data, $ip, $userAgent)
    {
        if (! $actor->hasPermission('catalog.manage')) {
            throw new IngredientException('PERMISSION_DENIED', 'Sin permiso para esta accion.', 403);
        }

        return DB::transaction(function () use ($actor, $id, $data, $ip, $userAgent) {
            $request = $this->requests->findForReviewOrFail($id);
            $this->assertPending($request);

            $old = $this->snapshot($request);
            if ($request->product && $request->product->status === 'pending_review') {
                $request->product->status = 'rejected';
                $request->product->is_active = true;
                $request->product->habilitado = 1;
                $request->product->save();
            }

            $updated = $this->requests->update($request, [
                'status' => ProductRequest::STATUS_REJECTED,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ]);

            $this->audit($actor->id, 'product_request_rejected', $updated->id, $old, $this->snapshot($updated), $ip, $userAgent);

            return $updated;
        });
    }

    private function prepareCreateData($actorId, array $data)
    {
        $name = preg_replace('/\s+/', ' ', trim($data['name']));

        return [
            'requested_by_user_id' => $actorId,
            'family_group_id' => $data['family_group_id'] ?? null,
            'name' => $name,
            'normalized_name' => $this->normalize($name),
            'brand' => $this->emptyToNull($data['brand'] ?? null),
            'presentation' => $this->emptyToNull($data['presentation'] ?? null),
            'barcode' => $this->emptyToNull($data['barcode'] ?? null),
            'unit_id' => $data['unit_id'] ?? null,
            'comment' => $this->emptyToNull($data['comment'] ?? null),
            'source' => $data['source'] ?? 'user_request',
            'status' => ProductRequest::STATUS_PENDING,
        ];
    }

    private function prepareProductData(ProductRequest $request, array $data)
    {
        $unitId = $data['default_unit_id'] ?? $request->unit_id;
        if (! $unitId || ! UnitMeasure::where('id', $unitId)->where('status', 'active')->exists()) {
            throw new IngredientException('PRODUCT_REQUEST_APPROVAL_INCOMPLETE', 'Completa una unidad activa para aprobar la solicitud.', 422);
        }

        $name = trim($data['name'] ?? $request->name);
        if ($name === '') {
            throw new IngredientException('PRODUCT_REQUEST_APPROVAL_INCOMPLETE', 'Completa el nombre del producto para aprobar la solicitud.', 422);
        }

        return [
            'name' => $name,
            'brand_id' => $data['brand_id'] ?? 0,
            'category_id' => $data['category_id'] ?? null,
            'ingredient_id' => $data['ingredient_id'] ?? null,
            'default_unit_id' => $unitId,
            'net_quantity' => $data['net_quantity'] ?? null,
            'package_unit_id' => $data['package_unit_id'] ?? null,
            'barcode' => $data['barcode'] ?? $request->barcode,
            'description' => $data['description'] ?? $request->comment,
            'status' => $data['status'] ?? 'active',
        ];
    }

    private function approvePendingProduct($product, array $data)
    {
        $unitId = $data['default_unit_id'] ?? $product->default_unit_id;
        if (! $unitId || ! UnitMeasure::where('id', $unitId)->where('status', 'active')->exists()) {
            throw new IngredientException('PRODUCT_REQUEST_APPROVAL_INCOMPLETE', 'Completa una unidad activa para aprobar la solicitud.', 422);
        }

        $product->fill([
            'name' => trim($data['name'] ?? $product->name),
            'normalized_name' => $this->normalize(trim($data['name'] ?? $product->name)),
            'brand_id' => $data['brand_id'] ?? $product->brand_id,
            'category_id' => $data['category_id'] ?? $product->category_id,
            'ingredient_id' => $data['ingredient_id'] ?? $product->ingredient_id,
            'default_unit_id' => $unitId,
            'net_quantity' => $data['net_quantity'] ?? $product->net_quantity,
            'package_unit_id' => $data['package_unit_id'] ?? $product->package_unit_id,
            'description' => $data['description'] ?? $product->description,
            'status' => 'active',
            'origin' => 'catalog',
            'family_group_id' => null,
            'is_active' => true,
            'habilitado' => 1,
        ]);
        if (Product::where('normalized_name', $product->normalized_name)
            ->where('status', 'active')
            ->where('id', '<>', $product->id)
            ->exists()) {
            throw new IngredientException('PRODUCT_NAME_ALREADY_EXISTS', 'Ya existe un producto activo con ese nombre.', 409);
        }

        $product->nombre = $product->name;
        $product->save();

        $barcode = $data['barcode'] ?? optional($product->barcodes()->where('status', 'active')->first())->barcode;
        if ($barcode) {
            $existing = ProductBarcode::where('barcode', $barcode)
                ->where('status', 'active')
                ->where('product_id', '<>', $product->id)
                ->exists();
            if ($existing) {
                throw new IngredientException('PRODUCT_BARCODE_ALREADY_EXISTS', 'Ya existe un producto activo con ese codigo de barras.', 409);
            }

            $current = ProductBarcode::where('product_id', $product->id)->orderBy('id')->first();
            if ($current) {
                $current->barcode = $barcode;
                $current->status = 'active';
                $current->save();
            } else {
                ProductBarcode::create([
                    'product_id' => $product->id,
                    'barcode' => $barcode,
                    'type' => null,
                    'status' => 'active',
                ]);
            }
        }

        return $product->fresh(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes']);
    }

    private function assertFamilyGroupAccess($userId, $familyGroupId)
    {
        if (! $familyGroupId) {
            return;
        }

        $exists = FamilyGroupMember::where('family_group_id', $familyGroupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $exists) {
            throw new IngredientException('FAMILY_GROUP_ACCESS_DENIED', 'No tenes acceso a este grupo familiar.', 403);
        }
    }

    private function assertPending(ProductRequest $request)
    {
        if ($request->status !== ProductRequest::STATUS_PENDING) {
            throw new IngredientException('PRODUCT_REQUEST_ALREADY_REVIEWED', 'La solicitud ya fue revisada.', 409);
        }
    }

    private function normalize($value)
    {
        return strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));
    }

    private function emptyToNull($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function snapshot(ProductRequest $request)
    {
        return [
            'name' => $request->name,
            'brand' => $request->brand,
            'presentation' => $request->presentation,
            'barcode' => $request->barcode,
            'unit_id' => $request->unit_id,
            'source' => $request->source,
            'status' => $request->status,
            'product_id' => $request->product_id,
            'reviewed_by_user_id' => $request->reviewed_by_user_id,
            'reviewed_at' => $request->reviewed_at ? (string) $request->reviewed_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'product_requests',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
