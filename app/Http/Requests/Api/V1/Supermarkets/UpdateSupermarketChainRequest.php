<?php

namespace App\Http\Requests\Api\V1\Supermarkets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupermarketChainRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'        => ['sometimes', 'string', 'max:150'],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:2000'],
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
