<?php

namespace App\Http\Resources\Api\V1\FamilyGroup;

use Illuminate\Http\Resources\Json\JsonResource;

class FamilyGroupMemberResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $this->whenLoaded('user', function () {
            $u = $this->user;
            if (!$u) return null;
            return [
                'id'   => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ];
        }, null);

        return [
            'id'        => $this->id,
            'user_id'   => $this->user_id,
            'name'      => $this->user ? $this->user->name : null,
            'email'     => $this->user ? $this->user->email : null,
            'role'      => $this->role_in_group,
            'status'    => $this->status,
            'joined_at' => $this->joined_at,
        ];
    }
}
