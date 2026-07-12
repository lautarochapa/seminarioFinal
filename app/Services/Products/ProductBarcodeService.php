<?php

namespace App\Services\Products;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\Products\ProductRepository;
use Illuminate\Support\Facades\DB;

class ProductBarcodeService
{
    private $products;

    public function __construct(ProductRepository $products)
    {
        $this->products = $products;
    }

    public function addBarcode(int $actorId, int $productId, string $barcode, string $ip, string $userAgent)
    {
        $product = $this->products->findOrFail($productId);

        if ($product->status !== 'active' || ! $product->is_active || $product->trashed()) {
            throw new IngredientException('PRODUCT_NOT_FOUND', 'El producto solicitado no existe.', 404);
        }

        if ($this->products->activeBarcodeExists($barcode)) {
            throw new IngredientException('PRODUCT_BARCODE_ALREADY_EXISTS', 'Ya existe un producto con ese codigo de barras.', 409);
        }

        return DB::transaction(function () use ($actorId, $product, $barcode, $ip, $userAgent) {
            $barcodeRecord = $this->products->addBarcode($product, $barcode);

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'product_barcode.created',
                'entity_name' => 'product_barcodes',
                'entity_id' => (string) $barcodeRecord->id,
                'old_values' => null,
                'new_values' => ['product_id' => $product->id, 'barcode' => $barcode],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            return $barcodeRecord;
        });
    }

    public function removeBarcode(int $actorId, int $productId, int $barcodeId, string $ip, string $userAgent)
    {
        $this->products->findOrFail($productId);
        $barcodeRecord = $this->products->findActiveBarcodeForProduct($productId, $barcodeId);

        DB::transaction(function () use ($actorId, $barcodeRecord, $ip, $userAgent) {
            $old = ['product_id' => $barcodeRecord->product_id, 'barcode' => $barcodeRecord->barcode];
            $this->products->deactivateBarcode($barcodeRecord);

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'product_barcode.deleted',
                'entity_name' => 'product_barcodes',
                'entity_id' => (string) $barcodeRecord->id,
                'old_values' => $old,
                'new_values' => null,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
        });
    }
}
