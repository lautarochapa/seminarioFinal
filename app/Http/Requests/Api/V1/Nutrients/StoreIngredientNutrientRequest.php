<?php

namespace App\Http\Requests\Api\V1\Nutrients;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngredientNutrientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nutrient_id'    => 'required|integer|exists:nutrients,id',
            'amount_per_100g' => 'required|numeric|min:0',
            'source'         => 'nullable|string|max:255',
            'status'         => 'nullable|string|in:active,inactive',
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
