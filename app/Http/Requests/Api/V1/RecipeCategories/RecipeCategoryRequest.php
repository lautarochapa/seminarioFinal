<?php

namespace App\Http\Requests\Api\V1\RecipeCategories;

use Illuminate\Foundation\Http\FormRequest;

class RecipeCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name'        => $required . '|string|max:150',
            'description' => 'sometimes|nullable|string',
            'parent_id'   => 'sometimes|nullable|integer|min:1',
            'status'      => 'sometimes|string|in:active,inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = ['name', 'description', 'parent_id', 'status'];
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, "El campo {$key} no esta permitido.");
                }
            }
        });
    }
}
