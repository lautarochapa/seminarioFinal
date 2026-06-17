<?php

namespace App\Http\Requests\Api\V1\MealPlanPortions;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealPlanPortionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'user_id'       => 'required|integer|min:1',
            'portion_factor'=> 'sometimes|numeric|min:0.01',
            'servings'      => 'sometimes|nullable|numeric|min:0',
            'notes'         => 'sometimes|nullable|string|max:1000',
        ];
    }
}
