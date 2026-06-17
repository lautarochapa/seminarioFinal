<?php

namespace App\Http\Resources\Api\V1\ShoppingSessions;

use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'shopping_list_id' => $this->shopping_list_id,
            'family_group_id' => $this->family_group_id,
            'user_id' => $this->user_id,
            'supermarket_branch_id' => $this->supermarket_branch_id,
            'started_at' => optional($this->started_at)->toIso8601String(),
            'finished_at' => optional($this->finished_at)->toIso8601String(),
            'status' => $this->status,
        ];
    }
}
