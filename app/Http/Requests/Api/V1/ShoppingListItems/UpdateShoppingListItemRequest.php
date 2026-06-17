<?php

namespace App\Http\Requests\Api\V1\ShoppingListItems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateShoppingListItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'ingredient_id' => 'sometimes|nullable|integer|min:1',
            'product_id' => 'sometimes|nullable|integer|min:1',
            'quantity' => 'sometimes|numeric|min:0.0001',
            'unit_id' => 'sometimes|integer|min:1',
            'estimated_price' => 'sometimes|nullable|numeric|min:0',
            'actual_price' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|string|in:pending,purchased,skipped,cancelled',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowed = ['ingredient_id', 'product_id', 'quantity', 'unit_id', 'estimated_price', 'actual_price', 'status', 'notes'];
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
