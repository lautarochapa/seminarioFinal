<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(function ($perm) {
                    return [
                        'id'     => $perm->id,
                        'code'   => $perm->code,
                        'module' => $perm->module,
                        'action' => $perm->action,
                    ];
                });
            }),
        ];
    }
}
