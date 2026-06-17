<?php

namespace App\Http\Requests\Api\V1\ShoppingListGeneration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GenerateFromHistoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, ['date_from', 'date_to'], true)) {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
