<?php

namespace App\Http\Requests\Api\V1\MealPlans;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMealPlanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'period_type'                    => 'sometimes|in:daily,weekly,monthly',
            'start_date'                     => 'sometimes|date',
            'end_date'                       => 'sometimes|date|after_or_equal:start_date',
            'mode'                           => 'sometimes|nullable|string|max:50',
            'items'                          => 'sometimes|array',
            'items.*.date'                   => 'required|date',
            'items.*.meal_type_id'           => 'required|integer|min:1',
            'items.*.recipe_id'              => 'sometimes|nullable|integer|min:1',
            'items.*.free_meal_description'  => 'sometimes|nullable|string|max:500',
            'items.*.is_eating_out'          => 'sometimes|boolean',
            'items.*.servings_total'         => 'sometimes|nullable|numeric|min:0',
            'items.*.notes'                  => 'sometimes|nullable|string|max:1000',
        ];
    }
}
