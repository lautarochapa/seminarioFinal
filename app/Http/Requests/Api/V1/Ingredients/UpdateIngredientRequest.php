<?php

namespace App\Http\Requests\Api\V1\Ingredients;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIngredientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:180',
            'category_id' => 'sometimes|nullable|integer|exists:ingredient_categories,id',
            'base_unit_id' => 'sometimes|nullable|integer|exists:unit_measures,id',
            'description' => 'sometimes|nullable|string',
            'is_generic' => 'sometimes|boolean',
            'is_preparation' => 'sometimes|boolean',
            'is_supplement' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:active,inactive',
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
