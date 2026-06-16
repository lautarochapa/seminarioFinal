<?php

namespace App\Http\Requests\Api\V1\Nutrients;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductNutrientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nutrient_id'        => 'required|integer|exists:nutrients,id',
            'amount_per_100g'    => 'nullable|numeric|min:0',
            'amount_per_serving' => 'nullable|numeric|min:0',
            'serving_size'       => 'nullable|numeric|min:0',
            'source'             => 'nullable|string|max:255',
            'status'             => 'nullable|string|in:active,inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $data = $this->validated();
            $hasValue = ! is_null($data['amount_per_100g'] ?? null)
                || ! is_null($data['amount_per_serving'] ?? null)
                || ! is_null($data['serving_size'] ?? null);

            if (! $hasValue) {
                $validator->errors()->add('amount_per_100g', 'Debe informar al menos un valor nutricional (amount_per_100g, amount_per_serving o serving_size).');
            }

            $allowed = array_keys($this->rules());
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'El campo no está permitido.');
                }
            }
        });
    }
}
