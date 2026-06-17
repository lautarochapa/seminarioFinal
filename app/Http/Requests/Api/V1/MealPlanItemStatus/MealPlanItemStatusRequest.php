<?php

namespace App\Http\Requests\Api\V1\MealPlanItemStatus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MealPlanItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servings' => 'sometimes|numeric|min:0.01|max:999',
            'notes' => 'sometimes|nullable|string|max:1000',
            'eating_out' => 'sometimes|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowed = ['servings', 'notes', 'eating_out'];
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
