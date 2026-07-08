<?php

namespace App\Http\Requests\Api\V1\HouseholdStock;

use Illuminate\Foundation\Http\FormRequest;

class ManualProductStockRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'product' => 'required|array',
            'product.name' => 'required|string|max:200',
            'product.brand' => 'nullable|string|max:150',
            'product.presentation' => 'nullable|string|max:150',
            'product.barcode' => 'nullable|string|max:80|regex:/^[A-Za-z0-9\-]+$/',
            'product.unit_id' => 'required|integer|min:1',
            'product.default_unit_id' => 'nullable|integer|min:1',
            'product.net_quantity' => 'nullable|numeric|min:0',
            'product.package_unit_id' => 'nullable|integer|min:1',
            'product.ingredient_id' => 'nullable|integer|min:1',
            'product.category_id' => 'nullable|integer|min:1',
            'stock' => 'required|array',
            'stock.quantity' => 'required|numeric|min:0.0001',
            'stock.unit_id' => 'nullable|integer|min:1',
            'stock.stock_location_id' => 'nullable|integer|min:1',
            'stock.expiration_date' => 'nullable|date',
            'stock.purchase_price' => 'nullable|numeric|min:0',
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
