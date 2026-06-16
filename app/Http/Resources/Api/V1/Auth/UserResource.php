<?php

namespace App\Http\Resources\Api\V1\Auth;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'lastname'          => $this->lastname,
            'username'          => $this->username,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'avatar_url'        => $this->avatar_url,
            'status'            => $this->status,
            'last_login_at'     => $this->last_login_at ? $this->last_login_at->toIso8601String() : null,
            'email_verified_at' => $this->email_verified_at ? $this->email_verified_at->toIso8601String() : null,
            'roles'             => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return ['code' => $role->code, 'name' => $role->name];
                });
            }),
            'created_at'        => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
