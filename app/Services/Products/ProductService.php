<?php

namespace App\Services\Products;

use App\AuditLog;
use App\Brand;
use App\Exceptions\Ingredients\IngredientException;
use App\Ingredient;
use App\Product;
use App\ProductCategory;
use App\Repositories\Products\ProductRepository;
use App\UnitMeasure;
use Illuminate\Support\Facades\DB;

class ProductService
{
    private $products;

    public function __construct(ProductRepository $products)
    {
        $this->products = $products;
    }

    public function list(array $filters)
    {
        return $this->products->paginate($filters);
    }

    public function publicList(array $filters)
    {
        return $this->products->paginate($filters, true);
    }

    public function show($id)
    {
        return $this->products->findOrFail($id);
    }

    public function publicShow($id)
    {
        return $this->products->findPublicOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $this->validateRelations($data);
        $data = $this->prepare($data, true);

        if ($this->products->activeNameExists($data['normalized_name'])) {
            throw new IngredientException('PRODUCT_NAME_ALREADY_EXISTS', 'Ya existe un producto activo con ese nombre.', 409);
        }

        if (! empty($data['barcode']) && $this->products->activeBarcodeExists($data['barcode'])) {
            throw new IngredientException('PRODUCT_BARCODE_ALREADY_EXISTS', 'Ya existe un producto activo con ese codigo de barras.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $barcode = $data['barcode'] ?? null;
            unset($data['barcode']);

            $product = $this->products->create($data);
            $this->products->syncBarcode($product, $barcode);
            $product = $this->products->findOrFail($product->id);
            $this->audit($actorId, 'product.created', $product->id, null, $this->auditPayload($product), $ip, $userAgent);

            return $product;
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $product = $this->products->findOrFail($id);
        $this->validateRelations($data);
        $data = $this->prepare($data, false, $product);

        if (array_key_exists('normalized_name', $data) && $this->products->activeNameExists($data['normalized_name'], $product->id)) {
            throw new IngredientException('PRODUCT_NAME_ALREADY_EXISTS', 'Ya existe un producto activo con ese nombre.', 409);
        }

        if (! empty($data['barcode']) && $this->products->activeBarcodeExists($data['barcode'], $product->id)) {
            throw new IngredientException('PRODUCT_BARCODE_ALREADY_EXISTS', 'Ya existe un producto activo con ese codigo de barras.', 409);
        }

        return DB::transaction(function () use ($actorId, $product, $data, $ip, $userAgent) {
            $old = $this->auditPayload($product);
            $barcode = array_key_exists('barcode', $data) ? $data['barcode'] : null;
            unset($data['barcode']);
            $updated = $this->products->update($product, $data);

            if ($barcode !== null) {
                $this->products->syncBarcode($updated, $barcode);
                $updated = $this->products->findOrFail($updated->id);
            }

            $new = $this->auditPayload($updated);
            if ($old != $new) {
                $this->audit($actorId, 'product.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $product = $this->products->findOrFail($id);

        return DB::transaction(function () use ($actorId, $product, $ip, $userAgent) {
            $old = $this->auditPayload($product);
            $product->status = 'inactive';
            $product->is_active = false;
            $product->habilitado = 0;
            $product->save();
            $product->delete();
            $deleted = $this->products->findWithTrashedOrFail($product->id);
            $new = $this->auditPayload($deleted);

            $this->audit($actorId, 'product.deleted', $deleted->id, $old, $new, $ip, $userAgent);

            return $deleted;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $product = $this->products->findWithTrashedOrFail($id);

        if (! $product->trashed()) {
            throw new IngredientException('RESOURCE_NOT_DELETED', 'El recurso no esta eliminado.', 409);
        }

        if ($this->products->activeNameExists($product->normalized_name, $product->id)) {
            throw new IngredientException('PRODUCT_NAME_ALREADY_EXISTS', 'Ya existe un producto activo con ese nombre.', 409);
        }

        return DB::transaction(function () use ($actorId, $product, $ip, $userAgent) {
            $old = $this->auditPayload($product);
            $product->restore();
            $product->status = 'active';
            $product->is_active = true;
            $product->habilitado = 1;
            $product->save();
            $restored = $this->products->findOrFail($product->id);
            $new = $this->auditPayload($restored);

            $this->audit($actorId, 'product.restored', $restored->id, $old, $new, $ip, $userAgent);

            return $restored;
        });
    }

    public function nutrition($id)
    {
        $product = $this->products->findPublicOrFail($id);

        return $this->products->nutrients($product->id);
    }

    public function prices($id)
    {
        $product = $this->products->findPublicOrFail($id);

        return $this->products->prices($product->id);
    }

    public function alternatives($id)
    {
        $product = $this->products->findPublicOrFail($id);

        return $this->products->alternatives($product);
    }

    private function validateRelations(array $data)
    {
        if (! empty($data['brand_id']) && ! Brand::where('id', $data['brand_id'])->where('status', 'active')->exists()) {
            throw new IngredientException('PRODUCT_BRAND_INVALID', 'La marca indicada no existe o no esta activa.', 422);
        }

        if (! empty($data['category_id']) && ! ProductCategory::where('id', $data['category_id'])->where('status', 'active')->exists()) {
            throw new IngredientException('PRODUCT_CATEGORY_INVALID', 'La categoria indicada no existe o no esta activa.', 422);
        }

        if (! empty($data['ingredient_id']) && ! Ingredient::where('id', $data['ingredient_id'])->where('status', 'active')->whereNull('deleted_at')->exists()) {
            throw new IngredientException('PRODUCT_INGREDIENT_INVALID', 'El ingrediente indicado no existe o no esta activo.', 422);
        }

        if (! empty($data['default_unit_id']) && ! UnitMeasure::where('id', $data['default_unit_id'])->where('status', 'active')->exists()) {
            throw new IngredientException('PRODUCT_UNIT_INVALID', 'La unidad indicada no existe o no esta activa.', 422);
        }
    }

    private function prepare(array $data, $creating, Product $product = null)
    {
        foreach (['name', 'description', 'barcode', 'status'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if ($creating || array_key_exists('name', $data)) {
            $name = $data['name'] ?? ($product ? $product->name : null);
            $data['name'] = preg_replace('/\s+/', ' ', trim((string) $name));
            $data['nombre'] = $data['name'];
            $data['normalized_name'] = $this->normalizeName($data['name']);
        }

        if (array_key_exists('barcode', $data)) {
            $data['codigo'] = $data['barcode'];
        }

        if (array_key_exists('status', $data)) {
            $data['is_active'] = $data['status'] === 'active';
            $data['habilitado'] = $data['status'] === 'active' ? 1 : 0;
        }

        if ($creating) {
            $data['status'] = $data['status'] ?? 'active';
            $data['is_active'] = $data['status'] === 'active';
            $data['habilitado'] = $data['status'] === 'active' ? 1 : 0;
            $data['img'] = $data['img'] ?? '';
            $data['supply_id'] = $data['supply_id'] ?? 0;
            $data['codigo'] = $data['codigo'] ?? ($data['barcode'] ?? 'product-'.$this->normalizeName($data['name']));
        }

        return array_intersect_key($data, array_flip([
            'name',
            'normalized_name',
            'brand_id',
            'category_id',
            'ingredient_id',
            'default_unit_id',
            'net_quantity',
            'description',
            'status',
            'is_active',
            'nombre',
            'codigo',
            'img',
            'habilitado',
            'supply_id',
            'barcode',
        ]));
    }

    private function normalizeName($name)
    {
        return strtolower(preg_replace('/\s+/', ' ', trim((string) $name)));
    }

    private function auditPayload(Product $product)
    {
        $product->loadMissing('barcodes');

        return [
            'name' => $product->name,
            'normalized_name' => $product->normalized_name,
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'ingredient_id' => $product->ingredient_id,
            'default_unit_id' => $product->default_unit_id,
            'net_quantity' => $product->net_quantity,
            'barcode' => optional($product->barcodes->first())->barcode,
            'description' => $product->description,
            'status' => $product->status,
            'is_active' => (bool) $product->is_active,
            'deleted_at' => $product->deleted_at ? (string) $product->deleted_at : null,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'products',
            'entity_id' => (string) $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
