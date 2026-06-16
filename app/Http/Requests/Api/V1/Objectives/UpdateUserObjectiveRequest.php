<?php

namespace App\Http\Requests\Api\V1\Objectives;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUserObjectiveRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'objective_id' => 'sometimes|integer|min:1',
            'priority' => 'sometimes|nullable|integer|min:1|max:999',
            'target_value' => 'sometimes|nullable|numeric',
            'target_unit' => 'sometimes|nullable|string|max:40',
            'target_date' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string|max:5000',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $blocked = ['user_id', 'id', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'];

            foreach ($blocked as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, 'El campo no está permitido.');
                }
            }
        });
    }
}
