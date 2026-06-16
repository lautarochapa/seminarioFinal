<?php

namespace App\Http\Requests\Api\V1\IngredientEquivalences;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIngredientEquivalenceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'source_ingredient_id' => 'sometimes|integer|exists:ingredients,id',
            'target_ingredient_id' => 'sometimes|integer|exists:ingredients,id',
            'equivalence_type' => 'sometimes|nullable|string|max:60',
            'conversion_factor' => 'sometimes|numeric|min:0.00000001',
            'reason' => 'sometimes|nullable|string',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }
}
