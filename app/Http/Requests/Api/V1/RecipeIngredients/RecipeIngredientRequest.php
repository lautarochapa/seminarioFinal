<?php

namespace App\Http\Requests\Api\V1\RecipeIngredients;

use Illuminate\Foundation\Http\FormRequest;

class RecipeIngredientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'ingredient_id'      => $required . '|integer|min:1',
            'unit_id'            => $required . '|integer|min:1',
            'quantity'           => $required . '|numeric|min:0.0001',
            'specific_product_id' => 'sometimes|nullable|integer|min:1',
            'notes'              => 'sometimes|nullable|string',
            'is_optional'        => 'sometimes|boolean',
            'sort_order'         => 'sometimes|integer|min:0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = ['ingredient_id', 'unit_id', 'quantity', 'specific_product_id', 'notes', 'is_optional', 'sort_order'];
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, "El campo {$key} no esta permitido.");
                }
            }
        });
    }
}
