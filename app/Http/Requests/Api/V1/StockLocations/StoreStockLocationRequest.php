<?php

namespace App\Http\Requests\Api\V1\StockLocations;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockLocationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:120',
            'type' => 'nullable|string|max:60',
            'status' => 'nullable|string|in:active,inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach (['family_group_id', 'deleted_at', 'created_at', 'updated_at'] as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, 'El campo '.$field.' no puede ser enviado.');
                }
            }
        });
    }
}
