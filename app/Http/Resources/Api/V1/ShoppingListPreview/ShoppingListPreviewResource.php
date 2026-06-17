<?php

namespace App\Http\Resources\Api\V1\ShoppingListPreview;

use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListPreviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'ingredient' => $this->resource['ingredient'],
            'missing_quantity' => (float) $this->resource['missing_quantity'],
            'required_quantity' => (float) $this->resource['required_quantity'],
            'unit' => $this->resource['unit'],
            'recipe_sources' => $this->resource['recipe_sources'],
            'incomplete' => (bool) $this->resource['incomplete'],
        ];
    }
}
