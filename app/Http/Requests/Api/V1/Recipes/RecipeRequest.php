<?php

namespace App\Http\Requests\Api\V1\Recipes;

use Illuminate\Foundation\Http\FormRequest;

class RecipeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name'              => $required . '|string|max:200',
            'description'       => 'sometimes|nullable|string',
            'servings'          => 'sometimes|nullable|integer|min:1|max:9999',
            'prep_time_minutes' => 'sometimes|nullable|integer|min:0|max:9999',
            'cook_time_minutes' => 'sometimes|nullable|integer|min:0|max:9999',
            'difficulty'        => 'sometimes|nullable|string|max:50',
            'category_id'       => 'sometimes|nullable|integer|min:1',
            'status'            => 'sometimes|string|in:active,inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = ['name', 'description', 'servings', 'prep_time_minutes', 'cook_time_minutes', 'difficulty', 'category_id', 'status'];
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, "El campo {$key} no esta permitido.");
                }
            }
        });
    }
}
