<?php

namespace App\Http\Resources\Api\V1\Products;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageResource extends JsonResource
{
    public function toArray($request)
    {
        $url = Str::startsWith($this->image_url, 'http')
            ? $this->image_url
            : Storage::disk('public')->url($this->image_url);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'image_url' => $url,
            'source' => $this->source,
            'is_primary' => (bool) $this->is_primary,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
