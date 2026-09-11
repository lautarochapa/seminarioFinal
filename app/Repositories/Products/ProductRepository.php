<?php

namespace App\Repositories\Products;

use App\Exceptions\Ingredients\IngredientException;
use App\Product;
use App\ProductBarcode;
use App\ProductImage;
use App\SupermarketProductPrice;

class ProductRepository
{
    public function paginate(array $filters, $publicOnly = false)
    {
        $relations = ['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes', 'images'];
        $familyGroupId = ! empty($filters['family_group_id']) ? (int) $filters['family_group_id'] : null;
        if ($familyGroupId) {
            $relations['stockItems'] = $this->stockItemsForGroup($familyGroupId);
        }
        $query = Product::with($relations);

        if ($publicOnly) {
            $familyGroupId = ! empty($filters['family_group_id']) ? (int) $filters['family_group_id'] : null;
            $query->where(function ($scope) use ($familyGroupId) {
                $scope->where(function ($active) {
                    $active->where('status', 'active')->where('is_active', true);
                });

                if ($familyGroupId) {
                    $scope->orWhere(function ($pending) use ($familyGroupId) {
                        $pending->where('status', 'pending_review')
                            ->where('origin', 'user_created')
                            ->where('family_group_id', $familyGroupId)
                            ->where('is_active', true);
                    });
                }
            });
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
        $product = Product::with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes', 'images'])->find($id);

        if (! $product) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        return $product;
    }

    public function findPublicOrFail($id, ?int $familyGroupId = null)
    {
        $relations = ['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes', 'images'];
        if ($familyGroupId) {
            $relations['stockItems'] = $this->stockItemsForGroup($familyGroupId);
        }

        $product = Product::with($relations)
            ->where('id', $id)
            ->where(function ($scope) use ($familyGroupId) {
                $scope->where(function ($active) {
                    $active->where('status', 'active')->where('is_active', true);
                });

                if ($familyGroupId) {
                    $scope->orWhere(function ($pending) use ($familyGroupId) {
                        $pending->where('status', 'pending_review')
                            ->where('origin', 'user_created')
                            ->where('family_group_id', $familyGroupId)
                            ->where('is_active', true);
                    });
                }
            })
            ->first();

        if (! $product) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        return $product;
    }

    public function findWithTrashedOrFail($id)
    {
        $product = Product::withTrashed()->with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes', 'images'])->find($id);

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
        return Product::create($data)->fresh(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes', 'images']);
    }

    public function update(Product $product, array $data)
    {
        $product->fill($data);
        $product->save();

        return $product->fresh(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes', 'images']);
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

    public function findPublicByBarcodeOrFail(string $barcode, ?int $familyGroupId = null)
    {
        $barcodeRecord = ProductBarcode::where('barcode', $barcode)
            ->where('status', 'active')
            ->first();

        if (! $barcodeRecord) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        $relations = ['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes', 'images'];
        if ($familyGroupId) {
            $relations['stockItems'] = function ($stock) use ($familyGroupId) {
                $stock->with(['location', 'unit'])
                    ->where('family_group_id', $familyGroupId)
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->orderByRaw('expiration_date is null')
                    ->orderBy('expiration_date')
                    ->orderBy('id');
            };
        }

        $product = Product::with($relations)
            ->where('id', $barcodeRecord->product_id)
            ->where(function ($scope) use ($familyGroupId) {
                $scope->where(function ($active) {
                    $active->where('status', 'active')->where('is_active', true);
                });

                if ($familyGroupId) {
                    $scope->orWhere(function ($pending) use ($familyGroupId) {
                        $pending->where('status', 'pending_review')
                            ->where('origin', 'user_created')
                            ->where('family_group_id', $familyGroupId)
                            ->where('is_active', true);
                    });
                }
            })
            ->first();

        if (! $product) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        return $product;
    }

    private function stockItemsForGroup(int $familyGroupId): callable
    {
        return function ($stock) use ($familyGroupId) {
            $stock->with(['location', 'unit'])
                ->where('family_group_id', $familyGroupId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderByRaw('expiration_date is null')
                ->orderBy('expiration_date')
                ->orderBy('id');
        };
    }

    public function findActiveBarcodeForProduct(int $productId, int $barcodeId)
    {
        $barcode = ProductBarcode::where('id', $barcodeId)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->first();

        if (! $barcode) {
            throw new IngredientException('PRODUCT_BARCODE_NOT_FOUND', 'El codigo de barras no existe para este producto.', 404);
        }

        return $barcode;
    }

    public function barcodeExistsForProduct(int $productId, string $barcode)
    {
        return ProductBarcode::where('product_id', $productId)
            ->where('barcode', $barcode)
            ->where('status', 'active')
            ->exists();
    }

    public function addBarcode(Product $product, string $barcode)
    {
        return ProductBarcode::create([
            'product_id' => $product->id,
            'barcode' => $barcode,
            'type' => null,
            'status' => 'active',
        ]);
    }

    public function deactivateBarcode(ProductBarcode $barcodeRecord)
    {
        $barcodeRecord->status = 'inactive';
        $barcodeRecord->save();
    }

    public function addImage(Product $product, array $data)
    {
        return ProductImage::create(array_merge($data, [
            'product_id' => $product->id,
            'status' => 'active',
        ]));
    }

    public function findActiveImageForProduct(int $productId, int $imageId)
    {
        $image = ProductImage::where('id', $imageId)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->first();

        if (! $image) {
            throw new IngredientException('PRODUCT_IMAGE_NOT_FOUND', 'La imagen no existe para este producto.', 404);
        }

        return $image;
    }

    public function clearPrimaryImages(int $productId)
    {
        ProductImage::where('product_id', $productId)
            ->where('is_primary', true)
            ->where('status', 'active')
            ->update(['is_primary' => false]);
    }

    public function deactivateImage(ProductImage $image)
    {
        $image->status = 'inactive';
        $image->save();
    }

    public function alternatives(Product $product)
    {
        $query = Product::with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes', 'images'])
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
