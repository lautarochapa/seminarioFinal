<?php

namespace App\Http\Resources\Api\V1\RecipeTags;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeTagResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'name'        => $this->name,
            'description' => $this->description,
            'type'        => $this->type,
            'status'      => $this->status,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
