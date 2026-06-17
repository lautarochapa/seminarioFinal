<?php

namespace App\Http\Requests\Api\V1\AiFoundation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TestSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'context' => 'required|string|min:1|max:500',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->all()) as $key) {
                if ($key !== 'context') {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
