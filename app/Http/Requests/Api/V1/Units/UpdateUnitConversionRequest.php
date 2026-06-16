<?php

namespace App\Http\Requests\Api\V1\Units;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitConversionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'from_unit_id' => 'sometimes|integer|exists:unit_measures,id',
            'to_unit_id' => 'sometimes|integer|exists:unit_measures,id',
            'ingredient_id' => 'sometimes|nullable|integer|exists:ingredients,id',
            'factor' => 'sometimes|numeric|min:0.00000001',
            'notes' => 'sometimes|nullable|string',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }
}
