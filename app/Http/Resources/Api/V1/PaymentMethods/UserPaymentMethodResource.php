<?php

namespace App\Http\Resources\Api\V1\PaymentMethods;

use Illuminate\Http\Resources\Json\JsonResource;

class UserPaymentMethodResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'payment_method_id' => $this->payment_method_id,
            'alias'             => $this->alias,
            'status'            => $this->status,
            'payment_method'    => $this->whenLoaded('paymentMethod', function () {
                return $this->paymentMethod ? [
                    'id'     => $this->paymentMethod->id,
                    'name'   => $this->paymentMethod->name,
                    'type'   => $this->paymentMethod->type,
                    'issuer' => $this->paymentMethod->issuer,
                ] : null;
            }),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
