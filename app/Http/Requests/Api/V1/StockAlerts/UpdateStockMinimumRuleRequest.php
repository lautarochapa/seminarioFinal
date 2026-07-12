<?php

namespace App\Http\Requests\Api\V1\StockAlerts;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockMinimumRuleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'product_id' => 'sometimes|nullable|integer|min:1',
            'ingredient_id' => 'sometimes|nullable|integer|min:1',
            'minimum_quantity' => 'sometimes|numeric|min:0',
            'unit_id' => 'sometimes|integer|min:1',
            'status' => 'sometimes|string|in:active,inactive',
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
