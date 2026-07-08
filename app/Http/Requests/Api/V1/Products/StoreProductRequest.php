<?php

namespace App\Http\Requests\Api\V1\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:200',
            'brand_id' => 'nullable|integer|min:1',
            'category_id' => 'nullable|integer|min:1',
            'ingredient_id' => 'nullable|integer|min:1',
            'barcode' => 'nullable|string|max:80',
            'description' => 'nullable|string',
            'net_quantity' => 'nullable|numeric|min:0',
            'default_unit_id' => 'nullable|integer|min:1',
            'package_unit_id' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:active,inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = ['name', 'brand_id', 'category_id', 'ingredient_id', 'barcode', 'description', 'net_quantity', 'default_unit_id', 'package_unit_id', 'status'];
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'El campo '.$key.' no esta permitido.');
                }
            }
        });
    }
}
