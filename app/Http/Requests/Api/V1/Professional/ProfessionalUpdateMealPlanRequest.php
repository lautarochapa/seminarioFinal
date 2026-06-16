<?php

namespace App\Http\Requests\Api\V1\Professional;

use Illuminate\Foundation\Http\FormRequest;

class ProfessionalUpdateMealPlanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'status'      => 'sometimes|string|max:50',
            'period_type' => 'sometimes|string|max:50',
            'start_date'  => 'sometimes|date',
            'end_date'    => 'sometimes|date|after_or_equal:start_date',
            'mode'        => 'sometimes|string|max:50',
            'config_json' => 'sometimes|array',
            'approved_at' => 'sometimes|nullable|date',
        ];
    }
}
