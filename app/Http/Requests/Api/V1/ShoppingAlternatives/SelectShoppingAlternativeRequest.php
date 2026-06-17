<?php

namespace App\Http\Requests\Api\V1\ShoppingAlternatives;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SelectShoppingAlternativeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'alternative_id' => 'required|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->all()) as $key) {
                if ($key !== 'alternative_id') {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
