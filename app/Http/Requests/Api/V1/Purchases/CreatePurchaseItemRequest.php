<?php

namespace App\Http\Requests\Api\V1\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseItemRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'product_id'      => 'required|integer|exists:products,id',
            'quantity'        => 'required|numeric|min:0.0001',
            'unit_id'         => 'required|integer|exists:unit_measures,id',
            'unit_price'      => 'nullable|numeric|min:0',
            'expiration_date' => 'nullable|date',
        ];
    }
}
