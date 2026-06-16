<?php

namespace App\Http\Resources\Api\V1\Professional;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalLinkResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                   => $this->id,
            'professional_user_id' => $this->professional_user_id,
            'can_view_profile'     => (bool) $this->can_view_profile,
            'can_view_stock'       => (bool) $this->can_view_stock,
            'can_view_meal_plans'  => (bool) $this->can_view_meal_plans,
            'can_edit_meal_plans'  => (bool) $this->can_edit_meal_plans,
            'can_view_reports'     => (bool) $this->can_view_reports,
            'status'               => $this->status,
            'granted_at'           => $this->granted_at ? $this->granted_at->toIso8601String() : null,
            'revoked_at'           => $this->revoked_at ? $this->revoked_at->toIso8601String() : null,
            'created_at'           => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
