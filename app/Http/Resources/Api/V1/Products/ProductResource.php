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
            'barcode' => $barcode,
            'description' => $this->description,
            'status' => $this->status,
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
            'images' => $this->whenLoaded('images', function () {
                return ProductImageResource::collection($this->images);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
