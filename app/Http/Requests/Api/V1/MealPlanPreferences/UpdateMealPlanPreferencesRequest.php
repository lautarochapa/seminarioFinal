<?php

namespace App\Http\Requests\Api\V1\MealPlanPreferences;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMealPlanPreferencesRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'avoid_repetition'  => 'sometimes|boolean',
            'respect_budget'    => 'sometimes|boolean',
            'respect_nutrition' => 'sometimes|boolean',
            'respect_stock'     => 'sometimes|boolean',
            'preferred_mode'    => 'sometimes|nullable|string|max:50',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach (['family_group_id', 'user_id', 'created_by', 'updated_by'] as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, 'El campo ' . $field . ' no puede ser enviado.');
                }
            }
        });
    }
}
