<?php

namespace App\Http\Requests\Api\V1\SupermarketProducts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupermarketProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'supermarket_branch_id' => [
                'required', 'integer',
                Rule::exists('supermarket_branches', 'id')->where('status', 'active')->whereNull('deleted_at'),
            ],
            'external_sku'    => ['nullable', 'string', 'max:255'],
            'source_url'      => ['nullable', 'url', 'max:2000'],
            'source_name'     => ['nullable', 'string', 'max:255'],
            'last_scraped_at' => ['nullable', 'date'],
        ];
    }
}
