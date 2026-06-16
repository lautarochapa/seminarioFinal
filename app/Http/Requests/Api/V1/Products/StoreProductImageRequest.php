<?php

namespace App\Http\Requests\Api\V1\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductImageRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'url' => ['nullable', 'url', 'max:2000'],
            'source' => ['nullable', 'string', 'max:80'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            if (! $this->hasFile('image') && ! $this->filled('url')) {
                $v->errors()->add('image', 'Debe proporcionar una imagen o una URL.');
            }
        });
    }
}
