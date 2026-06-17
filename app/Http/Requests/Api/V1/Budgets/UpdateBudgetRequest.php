<?php

namespace App\Http\Requests\Api\V1\Budgets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'total_amount' => 'sometimes|numeric|min:0.01',
            'currency'     => 'sometimes|string|in:ARS,USD,EUR',
        ];
    }
}
