<?php

namespace App\Http\Requests\Api\V1\IngredientCategories;

use Illuminate\Foundation\Http\FormRequest;

class IngredientCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'code' => 'sometimes|nullable|string|max:120',
            'name' => $required . '|string|max:150',
            'description' => 'sometimes|nullable|string',
            'parent_id' => 'sometimes|nullable|integer|min:1',
            'status' => 'sometimes|string|in:active,inactive',
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = ['code', 'name', 'description', 'parent_id', 'status', 'sort_order'];
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, "El campo {$key} no está permitido.");
                }
            }
        });
    }
}
