<?php

namespace App\Http\Resources\Api\V1\UserProfile;

use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray($request)
    {
        $user       = $this->resource->user;
        $profile    = $this->resource->profile;
        $objectives = $this->resource->objectives;

        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'lastname'          => $user->lastname,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'birth_date'        => $profile ? optional($profile->birth_date)->format('Y-m-d') : null,
            'gender'            => $profile ? $profile->gender : null,
            'height_cm'         => $profile && $profile->height_cm !== null ? (int) $profile->height_cm : null,
            'current_weight_kg' => $profile && $profile->current_weight_kg !== null ? (float) $profile->current_weight_kg : null,
            'target_weight_kg'  => $profile && $profile->target_weight_kg !== null ? (float) $profile->target_weight_kg : null,
            'activity_level'    => $profile ? $profile->activity_level : null,
            'meals_per_day'     => $profile && $profile->meals_per_day !== null ? (int) $profile->meals_per_day : null,
            'notes'             => $profile ? $profile->notes : null,
            'objectives'        => collect($objectives)->values()->toArray(),
            'preferences'       => [
                'uses_app_for_health'       => $profile ? (bool) $profile->uses_app_for_health : false,
                'uses_app_for_budget'       => $profile ? (bool) $profile->uses_app_for_budget : false,
                'uses_app_for_organization' => $profile ? (bool) $profile->uses_app_for_organization : false,
            ],
        ];
    }
}
