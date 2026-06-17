<?php

namespace App\Http\Requests\Api\V1\UserSupplements;

use Illuminate\Foundation\Http\FormRequest;

class CreateSupplementLogRequest extends FormRequest
{
    public function authorize() { return $this->user() !== null; }

    public function rules()
    {
        return [
            'taken_at'     => 'nullable|date|before_or_equal:now',
            'dose_quantity' => 'nullable|numeric|min:0.0001',
            'dose_unit_id'  => 'nullable|integer|min:1|exists:unit_measures,id',
            'notes'         => 'nullable|string|max:2000',
        ];
    }
}
