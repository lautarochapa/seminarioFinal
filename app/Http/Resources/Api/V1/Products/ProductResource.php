<?php

namespace App\Http\Resources\Api\V1\Products;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        $barcode = $this->relationLoaded('barcodes') && $this->barcodes ? optional($this->barcodes->first())->barcode : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'normalized_name' => $this->normalized_name,
            'brand_id' => $this->brand_id,
            'category_id' => $this->category_id,
            'ingredient_id' => $this->ingredient_id,
            'default_unit_id' => $this->default_unit_id,
            'net_quantity' => $this->net_quantity,
            'package_unit_id' => $this->package_unit_id,
            'barcode' => $barcode,
            'description' => $this->description,
            'status' => $this->status,
            'origin' => $this->origin,
            'review_status' => $this->status === 'pending_review' ? 'pending_review' : null,
            'family_group_id' => $this->family_group_id,
            'brand' => $this->whenLoaded('brand', function () {
                return $this->brand ? ['id' => $this->brand->id, 'name' => $this->brand->name] : null;
            }),
            'category' => $this->whenLoaded('commercialCategory', function () {
                return $this->commercialCategory ? ['id' => $this->commercialCategory->id, 'name' => $this->commercialCategory->name] : null;
            }),
            'ingredient' => $this->whenLoaded('ingredient', function () {
                return $this->ingredient ? ['id' => $this->ingredient->id, 'name' => $this->ingredient->name] : null;
            }),
            'unit' => $this->whenLoaded('defaultUnit', function () {
                return $this->defaultUnit ? [
                    'id' => $this->defaultUnit->id,
                    'code' => $this->defaultUnit->code,
                    'name' => $this->defaultUnit->name,
                    'symbol' => $this->defaultUnit->symbol,
                ] : null;
            }),
            'package_unit' => $this->whenLoaded('packageUnit', function () {
                return $this->packageUnit ? [
                    'id' => $this->packageUnit->id,
                    'code' => $this->packageUnit->code,
                    'name' => $this->packageUnit->name,
                    'symbol' => $this->packageUnit->symbol,
                ] : null;
            }),
            'images' => $this->whenLoaded('images', function () {
                return ProductImageResource::collection($this->images);
            }),
            'stock_items' => $this->whenLoaded('stockItems', function () {
                return $this->stockItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'family_group_id' => $item->family_group_id,
                        'product_id' => $item->product_id,
                        'stock_location_id' => $item->stock_location_id,
                        'quantity' => $item->quantity,
                        'unit_id' => $item->unit_id,
                        'expiration_date' => $item->expiration_date ? $item->expiration_date->toDateString() : null,
                        'status' => $item->status,
                        'location' => $item->relationLoaded('location') && $item->location ? [
                            'id' => $item->location->id,
                            'name' => $item->location->name,
                            'type' => $item->location->type,
                        ] : null,
                        'unit' => $item->relationLoaded('unit') && $item->unit ? [
                            'id' => $item->unit->id,
                            'code' => $item->unit->code,
                            'name' => $item->unit->name,
                            'symbol' => $item->unit->symbol,
                        ] : null,
                    ];
                })->values();
            }),
            'stock_summary' => $this->whenLoaded('stockItems', function () {
                return [
                    'in_stock' => $this->stockItems->count() > 0,
                    'items_count' => $this->stockItems->count(),
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
