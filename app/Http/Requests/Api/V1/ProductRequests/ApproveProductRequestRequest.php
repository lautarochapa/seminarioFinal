<?php

namespace App\Http\Requests\Api\V1\ProductRequests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveProductRequestRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'nullable|string|max:200',
            'brand_id' => 'nullable|integer|min:1',
            'category_id' => 'nullable|integer|min:1',
            'ingredient_id' => 'nullable|integer|min:1',
            'default_unit_id' => 'required|integer|min:1',
            'net_quantity' => 'nullable|numeric|min:0',
            'package_unit_id' => 'nullable|integer|min:1',
            'barcode' => 'nullable|string|max:80|regex:/^[A-Za-z0-9\-]+$/',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|string|in:active,inactive',
            'review_notes' => 'nullable|string|max:1000',
        ];
    }
}
