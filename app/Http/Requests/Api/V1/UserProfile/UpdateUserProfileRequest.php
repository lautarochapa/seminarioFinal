<?php

namespace App\Http\Requests\Api\V1\UserProfile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'               => 'sometimes|string|max:150',
            'lastname'           => 'sometimes|string|max:150',
            'phone'              => 'sometimes|nullable|string|max:50',
            'birth_date'         => 'sometimes|nullable|date|before:today',
            'gender'             => 'sometimes|nullable|string|in:male,female,other,prefer_not_to_say',
            'height_cm'          => 'sometimes|nullable|numeric|min:0',
            'current_weight_kg'  => 'sometimes|nullable|numeric|min:0',
            'target_weight_kg'   => 'sometimes|nullable|numeric|min:0',
            'activity_level'     => 'sometimes|nullable|string|in:sedentary,light,moderate,active,very_active',
            'meals_per_day'      => 'sometimes|nullable|integer|min:1',
            'notes'              => 'sometimes|nullable|string',
            'objective_ids'      => 'sometimes|array',
            'objective_ids.*'    => [
                'integer',
                Rule::exists('objectives', 'id')
                    ->where('status', 'active')
                    ->whereNull('deleted_at'),
            ],
            'preferences'                           => 'sometimes|array',
            'preferences.uses_app_for_health'       => 'sometimes|boolean',
            'preferences.uses_app_for_budget'       => 'sometimes|boolean',
            'preferences.uses_app_for_organization' => 'sometimes|boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Reject protected fields
            $protected = [
                'email', 'id', 'password', 'status', 'nivel_acceso',
                'remember_token', 'email_verified_at', 'avatar_url', 'username',
            ];
            foreach ($protected as $field) {
                if ($this->has($field)) {
                    $validator->errors()->add($field, "El campo {$field} no puede ser modificado directamente.");
                }
            }

            // Reject unknown preference keys
            if ($this->has('preferences') && is_array($this->input('preferences'))) {
                $allowed = ['uses_app_for_health', 'uses_app_for_budget', 'uses_app_for_organization'];
                foreach (array_keys($this->input('preferences')) as $key) {
                    if (!in_array($key, $allowed)) {
                        $validator->errors()->add(
                            "preferences.{$key}",
                            "La clave de preferencia '{$key}' no está permitida."
                        );
                    }
                }
            }
        });
    }
}
