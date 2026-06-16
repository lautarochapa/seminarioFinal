<?php

namespace App\Repositories\Nutrients;

use App\Exceptions\Nutrients\NutrientException;
use App\Product;
use App\ProductNutrient;

class ProductNutrientRepository
{
    public function findProductOrFail($productId)
    {
        $product = Product::where('id', $productId)->where('status', 'active')->whereNull('deleted_at')->first();

        if (! $product) {
            throw new NutrientException('NUTRIENT_PRODUCT_NOT_FOUND', 'El producto solicitado no existe o no está activo.', 404);
        }

        return $product;
    }

    public function listForProduct($productId)
    {
        return ProductNutrient::with(['nutrient.unit'])
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
    }

    public function activeRelationExists($productId, $nutrientId)
    {
        return ProductNutrient::where('product_id', $productId)
            ->where('nutrient_id', $nutrientId)
            ->where('status', 'active')
            ->exists();
    }

    public function create(array $data)
    {
        return ProductNutrient::create($data)->fresh(['nutrient.unit']);
    }
}
