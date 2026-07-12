<?php

namespace App\Http\Requests\Api\V1\Nutrients;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIngredientNutrientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'amount_per_100g' => 'sometimes|numeric|min:0',
            'source'         => 'sometimes|nullable|string|max:255',
            'status'         => 'sometimes|string|in:active,inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = array_keys($this->rules());
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'El campo no está permitido.');
                }
            }
        });
    }
}
