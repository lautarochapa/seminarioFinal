<?php

namespace App\Http\Requests\Api\V1\Units;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitConversionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'from_unit_id' => 'required|integer|exists:unit_measures,id',
            'to_unit_id' => 'required|integer|exists:unit_measures,id',
            'ingredient_id' => 'nullable|integer|exists:ingredients,id',
            'factor' => 'required|numeric|min:0.00000001',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
        ];
    }
}
