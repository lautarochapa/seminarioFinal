<?php

namespace App\Http\Requests\Api\V1\ShoppingListItems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreShoppingListItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'ingredient_id' => 'nullable|integer|min:1',
            'product_id' => 'nullable|integer|min:1',
            'free_text_name' => 'nullable|string|max:180',
            'quantity' => 'nullable|numeric|min:0.0001',
            'unit_id' => 'nullable|integer|min:1',
            'estimated_price' => 'nullable|numeric|min:0',
            'actual_price' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:pending,purchased,skipped,cancelled',
            'sort_order' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowed = ['ingredient_id', 'product_id', 'free_text_name', 'quantity', 'unit_id', 'estimated_price', 'actual_price', 'status', 'sort_order', 'notes'];
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }

            if (!$this->filled('ingredient_id') && !$this->filled('product_id') && trim((string) $this->input('free_text_name', '')) === '') {
                $validator->errors()->add('free_text_name', 'Escribi que necesitas comprar o selecciona un producto o ingrediente.');
            }
        });
    }
}
