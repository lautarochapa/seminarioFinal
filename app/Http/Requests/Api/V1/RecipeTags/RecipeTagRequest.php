<?php

namespace App\Http\Requests\Api\V1\RecipeTags;

use Illuminate\Foundation\Http\FormRequest;

class RecipeTagRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'code'        => $required . '|string|max:80',
            'name'        => $required . '|string|max:150',
            'description' => 'sometimes|nullable|string',
            'type'        => 'sometimes|nullable|string|max:60',
            'status'      => 'sometimes|string|in:active,inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = ['code', 'name', 'description', 'type', 'status'];
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, "El campo {$key} no esta permitido.");
                }
            }
        });
    }
}
