<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Support\Sanitizer;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
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
            'id'          => $this->id,
            'user_id'     => $this->user_id,
            'action'      => $this->action,
            'resource'    => $this->entity_name,
            'resource_id' => $this->entity_id !== null ? (int) $this->entity_id : null,
            'user'        => $user,
            'before'      => Sanitizer::redact($this->old_values),
            'after'       => Sanitizer::redact($this->new_values),
            'metadata'    => null,
            'ip'          => $this->ip_address,
            'user_agent'  => $this->user_agent,
            'created_at'  => $this->created_at,
        ];
    }
}
