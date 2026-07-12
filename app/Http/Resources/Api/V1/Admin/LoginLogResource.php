<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class LoginLogResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $this->whenLoaded('user', function () {
            $u = $this->user;
            if (is_null($u)) {
                return null;
            }
            return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email];
        }, null);

        return [
            'id'             => $this->id,
            'user'           => $user,
            'email'          => $this->email,
            'success'        => (bool) $this->success,
            'ip'             => $this->ip_address,
            'user_agent'     => $this->user_agent,
            'failure_reason' => $this->failure_reason,
            'created_at'     => $this->created_at,
        ];
    }
}
