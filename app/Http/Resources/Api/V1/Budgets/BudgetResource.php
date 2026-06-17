<?php

namespace App\Http\Resources\Api\V1\Budgets;

use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    private $used;
    private $available;
    private $percent;

    public function withUsage(float $used, float $available, float $percent): self
    {
        $this->used      = $used;
        $this->available = $available;
        $this->percent   = $percent;
        return $this;
    }

    public function toArray($request)
    {
        $data = [
            'id'               => $this->id,
            'family_group_id'  => $this->family_group_id,
            'year'             => $this->year,
            'month'            => $this->month,
            'total_amount'     => (float) $this->total_amount,
            'currency'         => $this->currency,
            'status'           => $this->status,
            'created_at'       => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at'       => $this->updated_at ? $this->updated_at->toISOString() : null,
            'deleted_at'       => $this->deleted_at ? $this->deleted_at->toISOString() : null,
        ];

        if ($this->used !== null) {
            $data['used_amount']      = $this->used;
            $data['available_amount'] = $this->available;
            $data['consumed_percent'] = $this->percent;
        }

        return $data;
    }
}
