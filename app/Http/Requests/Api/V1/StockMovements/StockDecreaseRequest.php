<?php

namespace App\Http\Requests\Api\V1\StockMovements;

use Illuminate\Foundation\Http\FormRequest;

class StockDecreaseRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'quantity' => 'required|numeric|min:0.0001',
            'reason' => $this->is('api/v1/family-groups/*/stock/*/discard') ? 'required|string|max:1000' : 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach (['family_group_id', 'user_id', 'status', 'created_by', 'created_at', 'movement_type'] as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, 'El campo '.$field.' no puede ser enviado.');
                }
            }
        });
    }
}
