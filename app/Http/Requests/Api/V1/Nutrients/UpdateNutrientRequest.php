<?php

namespace App\Http\Requests\Api\V1\Nutrients;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNutrientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code'        => 'sometimes|string|max:80',
            'name'        => 'sometimes|string|max:150',
            'unit_id'     => 'sometimes|integer|exists:unit_measures,id',
            'description' => 'sometimes|nullable|string',
            'status'      => 'sometimes|string|in:active,inactive',
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
