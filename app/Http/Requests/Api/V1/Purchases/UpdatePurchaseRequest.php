<?php

namespace App\Http\Requests\Api\V1\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'purchase_date'          => 'sometimes|date',
            'supermarket_branch_id'  => 'sometimes|nullable|integer|exists:supermarket_branches,id',
            'payment_method_id'      => 'sometimes|nullable|integer|exists:payment_methods,id',
            'shopping_list_id'       => 'sometimes|nullable|integer|exists:shopping_lists,id',
            'estimated_total'        => 'sometimes|nullable|numeric|min:0',
            'items'                  => 'sometimes|array|min:1',
            'items.*.product_id'     => 'required_with:items|integer|exists:products,id',
            'items.*.quantity'       => 'required_with:items|numeric|min:0.0001',
            'items.*.unit_id'        => 'required_with:items|integer|exists:unit_measures,id',
            'items.*.unit_price'     => 'nullable|numeric|min:0',
            'items.*.expiration_date'=> 'nullable|date',
        ];
    }
}
