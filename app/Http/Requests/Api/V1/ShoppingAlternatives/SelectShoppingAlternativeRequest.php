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
            'alternative_id' => 'nullable|required_without:supermarket_product_id|integer|min:1',
            'supermarket_product_id' => 'nullable|required_without:alternative_id|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('alternative_id') && $this->filled('supermarket_product_id')) {
                $validator->errors()->add('supermarket_product_id', 'Selecciona una sola referencia de alternativa.');
            }
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, ['alternative_id', 'supermarket_product_id'], true)) {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
