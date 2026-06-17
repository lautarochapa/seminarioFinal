<?php

namespace App\Http\Requests\Api\V1\MealPlanGeneration;

use Illuminate\Foundation\Http\FormRequest;

class GenerateMealPlanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'period_type' => 'required|in:daily,weekly,monthly',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
        ];
    }
}
