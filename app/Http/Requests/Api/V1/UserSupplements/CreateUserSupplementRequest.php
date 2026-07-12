<?php

namespace App\Http\Requests\Api\V1\UserSupplements;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserSupplementRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null;
    }

    public function rules()
    {
        return [
            'supplement_type_id' => 'required|integer|min:1|exists:supplement_types,id',
            'product_id'         => 'nullable|integer|min:1|exists:products,id',
            'ingredient_id'      => 'nullable|integer|min:1|exists:ingredients,id',
            'dose_quantity'      => 'nullable|numeric|min:0.0001',
            'dose_unit_id'       => 'nullable|integer|min:1|exists:unit_measures,id',
            'frequency'          => 'nullable|string|max:80',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
            'notes'              => 'nullable|string|max:2000',
        ];
    }
}
