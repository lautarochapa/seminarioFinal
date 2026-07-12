<?php

namespace App\Http\Requests\Api\V1\Budgets;

use Illuminate\Foundation\Http\FormRequest;

class CreateBudgetRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'year'         => 'required|integer|min:2000|max:2100',
            'month'        => 'required|integer|min:1|max:12',
            'total_amount' => 'required|numeric|min:0.01',
            'currency'     => 'nullable|string|in:ARS,USD,EUR',
        ];
    }
}
