<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'module'      => $this->module,
            'action'      => $this->action,
            'description' => $this->description,
            'status'      => $this->status,
        ];
    }
}
