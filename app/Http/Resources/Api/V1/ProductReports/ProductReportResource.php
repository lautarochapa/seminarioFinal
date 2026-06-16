<?php

namespace App\Http\Resources\Api\V1\ProductReports;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductReportResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'report_type' => $this->report_type,
            'description' => $this->description,
            'status'      => $this->status,
            'product'     => $this->whenLoaded('product', function () {
                return $this->product ? [
                    'id'   => $this->product->id,
                    'name' => $this->product->name,
                ] : null;
            }),
            'user'        => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id'    => $this->user->id,
                    'name'  => $this->user->name,
                    'email' => $this->user->email,
                ] : null;
            }),
            'resolver'    => $this->whenLoaded('resolver', function () {
                return $this->resolver ? [
                    'id'   => $this->resolver->id,
                    'name' => $this->resolver->name,
                ] : null;
            }),
            'resolved_at' => $this->resolved_at,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
