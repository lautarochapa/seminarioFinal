<?php

namespace App\Http\Requests\Api\V1\UserSupplements;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserSupplementRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null;
    }

    public function rules()
    {
        return [
            'supplement_type_id' => 'sometimes|integer|min:1|exists:supplement_types,id',
            'product_id'         => 'sometimes|nullable|integer|min:1|exists:products,id',
            'ingredient_id'      => 'sometimes|nullable|integer|min:1|exists:ingredients,id',
            'dose_quantity'      => 'sometimes|nullable|numeric|min:0.0001',
            'dose_unit_id'       => 'sometimes|nullable|integer|min:1|exists:unit_measures,id',
            'frequency'          => 'sometimes|nullable|string|max:80',
            'start_date'         => 'sometimes|nullable|date',
            'end_date'           => 'sometimes|nullable|date',
            'notes'              => 'sometimes|nullable|string|max:2000',
        ];
    }
}
