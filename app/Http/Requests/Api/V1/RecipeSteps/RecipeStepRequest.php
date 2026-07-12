<?php

namespace App\Http\Requests\Api\V1\RecipeSteps;

use Illuminate\Foundation\Http\FormRequest;

class RecipeStepRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'description'       => $required . '|string',
            'step_number'       => 'sometimes|integer|min:1',
            'estimated_minutes' => 'sometimes|nullable|integer|min:1',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = ['description', 'step_number', 'estimated_minutes'];
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, "El campo {$key} no esta permitido.");
                }
            }
        });
    }
}
