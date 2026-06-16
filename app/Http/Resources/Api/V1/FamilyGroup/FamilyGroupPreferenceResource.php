<?php

namespace App\Http\Resources\Api\V1\FamilyGroup;

use Illuminate\Http\Resources\Json\JsonResource;

class FamilyGroupPreferenceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'family_group_id'              => $this->family_group_id,
            'default_budget_mode'          => $this->default_budget_mode,
            'default_shopping_mode'        => $this->default_shopping_mode,
            'default_recipe_priority_mode' => $this->default_recipe_priority_mode,
            'allow_auto_stock_discount'    => (bool) $this->allow_auto_stock_discount,
            'updated_at'                   => $this->updated_at,
        ];
    }
}
