<?php

namespace App\Http\Requests\Api\V1\ShoppingLists;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreShoppingListRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'source_type' => 'sometimes|string|in:manual,meal_plan,history',
            'meal_plan_id' => 'sometimes|nullable|integer|min:1',
            'status' => 'sometimes|string|in:draft,active,in_progress,completed,cancelled',
            'optimization_mode' => 'sometimes|nullable|string|max:60',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, ['source_type', 'meal_plan_id', 'status', 'optimization_mode'], true)) {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
