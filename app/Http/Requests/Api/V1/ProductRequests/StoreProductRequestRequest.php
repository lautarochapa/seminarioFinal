<?php

namespace App\Http\Requests\Api\V1\ProductRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequestRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:200',
            'brand' => 'nullable|string|max:150',
            'presentation' => 'nullable|string|max:150',
            'barcode' => 'nullable|string|max:80|regex:/^[A-Za-z0-9\-]+$/',
            'unit_id' => 'nullable|integer|min:1|exists:unit_measures,id',
            'family_group_id' => 'nullable|integer|min:1|exists:family_groups,id',
            'comment' => 'nullable|string|max:1000',
            'source' => 'nullable|string|in:user_request,barcode,stock,shopping_list',
        ];
    }

}
