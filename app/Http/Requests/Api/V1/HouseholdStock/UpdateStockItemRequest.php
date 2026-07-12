<?php

namespace App\Http\Requests\Api\V1\HouseholdStock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockItemRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'product_id' => 'sometimes|required|integer|min:1',
            'stock_location_id' => 'sometimes|nullable|integer|min:1',
            'quantity' => 'sometimes|required|numeric|min:0',
            'unit_id' => 'sometimes|required|integer|min:1',
            'expiration_date' => 'sometimes|nullable|date',
            'purchase_price' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|required|string|in:active,inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach (['family_group_id', 'user_id', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'] as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, 'El campo '.$field.' no puede ser enviado.');
                }
            }
        });
    }
}
