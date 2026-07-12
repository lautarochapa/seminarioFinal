<?php

namespace App\Http\Requests\Api\V1\IngredientEquivalences;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngredientEquivalenceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'source_ingredient_id' => 'required|integer|exists:ingredients,id',
            'target_ingredient_id' => 'required|integer|exists:ingredients,id',
            'equivalence_type' => 'nullable|string|max:60',
            'conversion_factor' => 'required|numeric|min:0.00000001',
            'reason' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
        ];
    }
}
