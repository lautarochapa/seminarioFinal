<?php

namespace App\Http\Requests\Api\V1\SupermarketProducts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupermarketProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'supermarket_branch_id' => [
                'sometimes', 'integer',
                Rule::exists('supermarket_branches', 'id')->where('status', 'active')->whereNull('deleted_at'),
            ],
            'external_sku'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_url'      => ['sometimes', 'nullable', 'url', 'max:2000'],
            'source_name'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_scraped_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
