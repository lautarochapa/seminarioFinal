<?php

namespace App\Services\ShoppingLists;

class PurchaseQuantity
{
    /** Only the purchase of whole pieces is rounded; stock and recipe quantities stay fractional. */
    public static function withoutPackaging(float $missingQuantity, ?string $unitCode): float
    {
        return $unitCode === 'unit' && $missingQuantity > 0
            ? (float) ceil($missingQuantity)
            : $missingQuantity;
    }
}
