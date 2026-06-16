<?php

namespace App\Http\Resources\Api\V1\Professional;

use Illuminate\Http\Resources\Json\JsonResource;

class LinkedUserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'link_id'             => $this->id,
            'user_id'             => $this->user_id,
            'user'                => [
                'id'       => $this->user->id,
                'name'     => $this->user->name,
                'lastname' => $this->user->lastname,
                'email'    => $this->user->email,
            ],
            'can_view_profile'    => (bool) $this->can_view_profile,
            'can_view_meal_plans' => (bool) $this->can_view_meal_plans,
            'can_edit_meal_plans' => (bool) $this->can_edit_meal_plans,
            'can_view_reports'    => (bool) $this->can_view_reports,
            'granted_at'          => $this->granted_at ? $this->granted_at->toIso8601String() : null,
        ];
    }
}
