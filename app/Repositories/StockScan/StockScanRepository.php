<?php

namespace App\Repositories\StockScan;

use App\Exceptions\Ingredients\IngredientException;
use App\Product;
use App\ProductBarcode;

class StockScanRepository
{
    public function findActiveProductByBarcodeOrFail(string $barcode): Product
    {
        $productBarcode = ProductBarcode::where('barcode', $barcode)
            ->where('status', 'active')
            ->first();

        if (! $productBarcode) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        $product = Product::with(['ingredient', 'defaultUnit'])
            ->where('id', $productBarcode->product_id)
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $product) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        return $product;
    }
}
