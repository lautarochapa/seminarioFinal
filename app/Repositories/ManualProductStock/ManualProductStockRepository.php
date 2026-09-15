<?php

namespace App\Repositories\ManualProductStock;

use App\Product;
use App\ProductBarcode;
use App\ProductRequest;
use App\StockItem;
use App\StockLocation;
use App\StockMovement;
use App\UnitMeasure;

class ManualProductStockRepository
{
    public function activeUnitExists(int $unitId): bool
    {
        return UnitMeasure::where('id', $unitId)->where('status', 'active')->exists();
    }

    public function activeLocationInGroupExists(int $groupId, int $locationId): bool
    {
        return StockLocation::where('id', $locationId)
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
    }

    public function findActiveProductByBarcode(string $barcode)
    {
        $record = ProductBarcode::where('barcode', $barcode)->where('status', 'active')->first();
        if (! $record) {
            return null;
        }

        return Product::with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes'])
            ->where('id', $record->product_id)
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();
    }

    public function barcodeExists(string $barcode): bool
    {
        return ProductBarcode::where('barcode', $barcode)->where('status', 'active')->exists();
    }

    public function findPendingProductByBarcode(int $groupId, string $barcode)
    {
        $record = ProductBarcode::where('barcode', $barcode)->where('status', 'active')->first();
        if (! $record) {
            return null;
        }

        return Product::with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes'])
            ->where('id', $record->product_id)
            ->where('status', 'pending_review')
            ->where('origin', 'user_created')
            ->where('family_group_id', $groupId)
            ->whereNull('deleted_at')
            ->first();
    }

    public function findPendingDuplicate(int $groupId, string $name, ?string $barcode, ?string $presentation)
    {
        $normalizedName = $this->normalize($name);

        $query = Product::with(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes'])
            ->where('status', 'pending_review')
            ->where('origin', 'user_created')
            ->where('family_group_id', $groupId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($normalizedName, $barcode, $presentation) {
                $q->where('name', 'ILIKE', $normalizedName);

                if ($presentation) {
                    $q->orWhere('description', 'ILIKE', '%'.$presentation.'%');
                }

                if ($barcode) {
                    $q->orWhereHas('barcodes', function ($barcodes) use ($barcode) {
                        $barcodes->where('barcode', $barcode)->where('status', 'active');
                    });
                }
            });

        return $query->first();
    }

    public function createProduct(array $data)
    {
        return Product::create($data)->fresh(['brand', 'commercialCategory', 'ingredient', 'defaultUnit', 'packageUnit', 'barcodes']);
    }

    public function addBarcode(Product $product, string $barcode): void
    {
        if (! ProductBarcode::where('product_id', $product->id)->where('barcode', $barcode)->exists()) {
            ProductBarcode::create([
                'product_id' => $product->id,
                'barcode' => $barcode,
                'type' => null,
                'status' => 'active',
            ]);
        }
    }

    public function findStockDuplicate(int $groupId, int $productId, ?int $locationId)
    {
        return StockItem::where('family_group_id', $groupId)
            ->where('product_id', $productId)
            ->where('stock_location_id', $locationId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
    }

    public function createStockItem(array $data)
    {
        return StockItem::create($data)->fresh(['product', 'location', 'unit']);
    }

    public function updateStockItem(StockItem $item, array $data)
    {
        $item->fill($data);
        $item->save();

        return $item->fresh(['product', 'location', 'unit']);
    }

    public function createMovement(array $data)
    {
        return StockMovement::create($data);
    }

    public function findPendingRequest(int $groupId, int $userId, Product $product)
    {
        return ProductRequest::where('family_group_id', $groupId)
            ->where('requested_by_user_id', $userId)
            ->where('product_id', $product->id)
            ->where('status', ProductRequest::STATUS_PENDING)
            ->first();
    }

    public function createRequest(array $data)
    {
        return ProductRequest::create($data)->fresh(['requester', 'familyGroup', 'unit', 'product']);
    }

    private function normalize($value)
    {
        // mb_strtolower: strtolower() corrompe el byte inicial de caracteres UTF-8
        // acentuados (0xC3 -> 0xE3) y PostgreSQL rechaza el ILIKE resultante.
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $value)), 'UTF-8');
    }
}
