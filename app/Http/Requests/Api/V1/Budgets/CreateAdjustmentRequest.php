<?php

namespace App\Http\Requests\Api\V1\Budgets;

use Illuminate\Foundation\Http\FormRequest;

class CreateAdjustmentRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null;
    }

    public function rules()
    {
        return [
            'amount'      => 'required|numeric|not_in:0',
            'description' => 'required|string|max:500',
        ];
    }
}
