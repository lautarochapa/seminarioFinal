<?php

namespace App\Http\Requests\Api\V1\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreBarcodeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'barcode' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('barcode') && is_string($this->barcode)) {
            $this->merge(['barcode' => trim($this->barcode)]);
        }
    }
}
