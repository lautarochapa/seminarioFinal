<?php

namespace App\Repositories\Products;

use App\Exceptions\Ingredients\IngredientException;
use App\Product;
use App\ProductBarcode;
use App\SupermarketProductPrice;

class ProductRepository
{
    public function paginate(array $filters, $publicOnly = false)
    {
        $query = Product::with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'barcodes']);

        if ($publicOnly) {
            $query->where('status', 'active')->where('is_active', true);
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['ingredient_id'])) {
            $query->where('ingredient_id', $filters['ingredient_id']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'ILIKE', $search)
                    ->orWhere('products.normalized_name', 'ILIKE', $search)
                    ->orWhere('products.codigo', 'ILIKE', $search)
                    ->orWhereHas('brand', function ($brand) use ($search) {
                        $brand->where('name', 'ILIKE', $search)
                            ->orWhere('nombre', 'ILIKE', $search);
                    })
                    ->orWhereHas('barcodes', function ($barcode) use ($search) {
                        $barcode->where('barcode', 'ILIKE', $search);
                    });
            });
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('products.created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('products.created_at', '<=', $filters['created_to']);
        }

        $sortMap = [
            'id' => 'products.id',
            'name' => 'products.name',
            'status' => 'products.status',
            'created_at' => 'products.created_at',
            'updated_at' => 'products.updated_at',
        ];
        $sort = $sortMap[$filters['sort'] ?? 'name'] ?? 'products.name';
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderBy($sort, $order)->orderBy('products.id')->paginate($perPage);
    }

    public function findOrFail($id)
    {
        $product = Product::with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'barcodes'])->find($id);

        if (! $product) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        return $product;
    }

    public function findPublicOrFail($id)
    {
        $product = Product::with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'barcodes'])
            ->where('id', $id)
            ->where('status', 'active')
            ->where('is_active', true)
            ->first();

        if (! $product) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        return $product;
    }

    public function findWithTrashedOrFail($id)
    {
        $product = Product::withTrashed()->with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'barcodes'])->find($id);

        if (! $product) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        return $product;
    }

    public function activeNameExists($normalizedName, $exceptId = null)
    {
        $query = Product::where('normalized_name', $normalizedName)->where('status', 'active');

        if ($exceptId) {
            $query->where('id', '<>', $exceptId);
        }

        return $query->exists();
    }

    public function activeBarcodeExists($barcode, $exceptProductId = null)
    {
        $query = ProductBarcode::where('barcode', $barcode)->where('status', 'active');

        if ($exceptProductId) {
            $query->where('product_id', '<>', $exceptProductId);
        }

        return $query->exists();
    }

    public function create(array $data)
    {
        return Product::create($data)->fresh(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'barcodes']);
    }

    public function update(Product $product, array $data)
    {
        $product->fill($data);
        $product->save();

        return $product->fresh(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'barcodes']);
    }

    public function syncBarcode(Product $product, $barcode)
    {
        if ($barcode === null || $barcode === '') {
            return;
        }

        $current = ProductBarcode::where('product_id', $product->id)->orderBy('id')->first();
        if ($current) {
            $current->barcode = $barcode;
            $current->status = 'active';
            $current->save();

            return;
        }

        ProductBarcode::create([
            'product_id' => $product->id,
            'barcode' => $barcode,
            'type' => null,
            'status' => 'active',
        ]);
    }

    public function nutrients($productId)
    {
        return \App\ProductNutrient::with(['nutrient.unit'])
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
    }

    public function prices($productId)
    {
        return SupermarketProductPrice::with(['supermarketProduct.chain', 'supermarketProduct.branch'])
            ->whereHas('supermarketProduct', function ($query) use ($productId) {
                $query->where('product_id', $productId)->where('status', 'active');
            })
            ->where('status', 'active')
            ->orderBy('scraped_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function alternatives(Product $product)
    {
        $query = Product::with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'barcodes'])
            ->where('id', '<>', $product->id)
            ->where('status', 'active')
            ->where('is_active', true);

        if ($product->ingredient_id) {
            $query->where('ingredient_id', $product->ingredient_id);
        } elseif ($product->category_id) {
            $query->where('category_id', $product->category_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->orderBy('name')->limit(20)->get();
    }
}
