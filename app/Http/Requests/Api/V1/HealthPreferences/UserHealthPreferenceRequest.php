<?php

namespace App\Http\Requests\Api\V1\HealthPreferences;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UserHealthPreferenceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'dietary_restriction_id' => 'sometimes|integer|min:1',
            'health_condition_id' => 'sometimes|integer|min:1',
            'allergy_id' => 'sometimes|integer|min:1',
            'severity' => 'sometimes|nullable|string|max:40',
            'notes' => 'sometimes|nullable|string',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            foreach (['user_id', 'created_by', 'updated_by', 'deleted_by', 'status', 'deleted_at'] as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, 'El campo no está permitido.');
                }
            }
        });
    }
}
