<?php

namespace App\Http\Requests\Api\V1\StockScan;

use Illuminate\Foundation\Http\FormRequest;

class StockScanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'barcode' => ['required', 'string', 'max:80', 'regex:/^[0-9]{6,32}$/'],
            'stock_location_id' => 'required|integer|min:1',
            'quantity' => 'required|numeric|min:0.0001',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach (['family_group_id', 'user_id', 'status', 'unit_id', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'] as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, 'El campo '.$field.' no puede ser enviado.');
                }
            }
        });
    }
}
