<?php

namespace App\Services\Products;

use App\Exceptions\Purchases\PurchaseException;
use App\Product;
use App\UnitConversion;
use App\UnitMeasure;

/** Keeps purchasable package counts separate from measurable stock contents. */
class ProductPackagingService
{
    public function isPackageUnit(int $unitId): bool
    {
        return UnitMeasure::where('id', $unitId)->where(function ($query) {
            $query->where('code', 'package')->orWhere('type', 'package');
        })->exists();
    }

    public function purchaseUnitId(Product $product): ?int
    {
        $default = $product->defaultUnit;
        if ($default && $default->status === 'active' && $this->isPackageUnit((int) $default->id)) {
            return (int) $default->id;
        }
        $id = UnitMeasure::where('code', 'package')->where('status', 'active')->value('id');
        return $id ? (int) $id : null;
    }

    public function hasContent(Product $product): bool
    {
        $unit = $product->packageUnit;
        return (float) $product->net_quantity > 0 && $unit && $unit->status === 'active'
            && !$this->isPackageUnit((int) $unit->id);
    }

    public function toStock(Product $product, float $quantity, int $unitId, ?float $unitPrice): array
    {
        if (!$this->isPackageUnit($unitId)) {
            return ['quantity' => $quantity, 'unit_id' => $unitId, 'unit_price' => $unitPrice];
        }
        if (!$this->hasContent($product)) {
            throw new PurchaseException('PRODUCT_PACKAGE_CONTENT_REQUIRED', 'El producto no tiene contenido y unidad de envase validos. Revisalos antes de ingresarlo al stock.', 422);
        }
        $size = (float) $product->net_quantity;
        return [
            'quantity' => round($quantity * $size, 4),
            'unit_id' => (int) $product->package_unit_id,
            'unit_price' => $unitPrice !== null ? $unitPrice / $size : null,
        ];
    }

    public function pricePerUnit(Product $product, int $unitId, float $packagePrice): ?float
    {
        if ($this->isPackageUnit($unitId)) {
            return $this->hasContent($product) ? $packagePrice : null;
        }
        if (!$this->hasContent($product)) {
            return $unitId === (int) $product->default_unit_id ? $packagePrice : null;
        }
        $size = $this->contentInUnit($product, $unitId);
        return $size !== null && $size > 0 ? $packagePrice / $size : null;
    }

    public function packagePriceFromUnit(Product $product, int $unitId, float $unitPrice): ?float
    {
        if ($this->isPackageUnit($unitId)) {
            return $unitPrice;
        }
        $size = $this->contentInUnit($product, $unitId);
        return $size !== null ? $unitPrice * $size : null;
    }

    private function contentInUnit(Product $product, int $unitId): ?float
    {
        if (!$this->hasContent($product)) {
            return null;
        }
        $contentUnitId = (int) $product->package_unit_id;
        if ($contentUnitId === $unitId) {
            return (float) $product->net_quantity;
        }
        foreach ([[ $contentUnitId, $unitId, false ], [ $unitId, $contentUnitId, true ]] as [$from, $to, $inverse]) {
            $conversion = UnitConversion::where('from_unit_id', $from)->where('to_unit_id', $to)
                ->where('status', 'active')->where('factor', '>', 0)
                ->where(function ($query) use ($product) {
                    $query->whereNull('ingredient_id');
                    if ($product->ingredient_id) $query->orWhere('ingredient_id', $product->ingredient_id);
                })->orderByRaw('CASE WHEN ingredient_id IS NOT NULL THEN 0 ELSE 1 END')->first();
            if ($conversion) {
                $factor = $inverse ? 1 / (float) $conversion->factor : (float) $conversion->factor;
                return (float) $product->net_quantity * $factor;
            }
        }
        return null;
    }
}
