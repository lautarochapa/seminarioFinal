<?php

namespace App\Http\Requests\Api\V1\UserProfile;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrioritySettingsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'health_weight'      => 'sometimes|numeric|min:0',
            'budget_weight'      => 'sometimes|numeric|min:0',
            'time_weight'        => 'sometimes|numeric|min:0',
            'stock_usage_weight' => 'sometimes|numeric|min:0',
            'preferred_mode'     => 'sometimes|nullable|string|max:50',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = ['health_weight', 'budget_weight', 'time_weight', 'stock_usage_weight', 'preferred_mode'];
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed)) {
                    $validator->errors()->add($key, "El campo '{$key}' no está permitido.");
                }
            }
        });
    }
}
