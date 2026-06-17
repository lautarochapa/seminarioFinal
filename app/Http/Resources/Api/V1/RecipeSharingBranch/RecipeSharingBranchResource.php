<?php

namespace App\Http\Resources\Api\V1\RecipeSharingBranch;

use Illuminate\Http\Resources\Json\JsonResource;

class RecipeSharingBranchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'is_public'               => $this->is_public,
            'is_official'             => $this->is_official,
            'is_verified'             => $this->is_verified,
            'status'                  => $this->status,
            'owner_user_id'           => $this->owner_user_id,
            'branched_from_recipe_id' => $this->branched_from_recipe_id,
            'source_type'             => $this->source_type,
            'difficulty'              => $this->difficulty,
            'servings'                => $this->servings,
            'category_id'             => $this->category_id,
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
        ];
    }
}
