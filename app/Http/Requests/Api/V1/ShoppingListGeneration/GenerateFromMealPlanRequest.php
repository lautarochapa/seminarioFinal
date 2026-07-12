<?php

namespace App\Http\Requests\Api\V1\ShoppingListGeneration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GenerateFromMealPlanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'meal_plan_id' => 'required|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->all()) as $key) {
                if ($key !== 'meal_plan_id') {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
