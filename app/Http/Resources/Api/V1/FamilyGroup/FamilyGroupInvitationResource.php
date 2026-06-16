<?php

namespace App\Http\Resources\Api\V1\FamilyGroup;

use Illuminate\Http\Resources\Json\JsonResource;

class FamilyGroupInvitationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'family_group_id' => $this->family_group_id,
            'invited_email'   => $this->invited_email,
            'status'          => $this->status,
            'expires_at'      => $this->expires_at,
            'accepted_at'     => $this->accepted_at,
            'created_at'      => $this->created_at,
        ];
    }
}
