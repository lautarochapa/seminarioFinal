<?php

namespace App\Http\Requests\Api\V1\ShoppingSessions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ScanShoppingSessionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'barcode' => 'required|string|max:80',
            'quantity' => 'sometimes|numeric|min:0.0001',
            'price' => 'sometimes|nullable|numeric|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, ['barcode', 'quantity', 'price'], true)) {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
