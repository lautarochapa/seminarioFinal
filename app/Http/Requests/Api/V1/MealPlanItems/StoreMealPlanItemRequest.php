<?php

namespace App\Http\Requests\Api\V1\MealPlanItems;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealPlanItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'                 => 'required|date',
            'meal_type_id'         => 'required|integer|min:1',
            'recipe_id'            => 'sometimes|nullable|integer|min:1',
            'free_meal_description'=> 'sometimes|nullable|string|max:500',
            'is_eating_out'        => 'sometimes|boolean',
            'servings_total'       => 'sometimes|nullable|numeric|min:0',
            'notes'                => 'sometimes|nullable|string|max:1000',
        ];
    }
}
