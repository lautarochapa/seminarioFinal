<?php

namespace App\Http\Requests\Api\V1\ShoppingSessions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FinishShoppingSessionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'stock_location_id' => 'sometimes|nullable|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->all()) as $key) {
                if ($key !== 'stock_location_id') {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
