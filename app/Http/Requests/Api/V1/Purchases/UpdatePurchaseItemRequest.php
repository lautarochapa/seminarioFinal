<?php

namespace App\Http\Requests\Api\V1\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseItemRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'product_id'      => 'sometimes|integer|exists:products,id',
            'quantity'        => 'sometimes|numeric|min:0.0001',
            'unit_id'         => 'sometimes|integer|exists:unit_measures,id',
            'unit_price'      => 'sometimes|nullable|numeric|min:0',
            'expiration_date' => 'sometimes|nullable|date',
        ];
    }
}
