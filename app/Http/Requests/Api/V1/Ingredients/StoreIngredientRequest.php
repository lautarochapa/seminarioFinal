<?php

namespace App\Http\Requests\Api\V1\Ingredients;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngredientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:180',
            'category_id' => 'nullable|integer|exists:ingredient_categories,id',
            'base_unit_id' => 'nullable|integer|exists:unit_measures,id',
            'description' => 'nullable|string',
            'is_generic' => 'nullable|boolean',
            'is_preparation' => 'nullable|boolean',
            'is_supplement' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = array_keys($this->rules());
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'El campo no estÃ¡ permitido.');
                }
            }
        });
    }
}
