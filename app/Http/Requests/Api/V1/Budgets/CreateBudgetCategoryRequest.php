<?php

namespace App\Http\Requests\Api\V1\Budgets;

use Illuminate\Foundation\Http\FormRequest;

class CreateBudgetCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null;
    }

    public function rules()
    {
        return [
            'product_category_id'    => 'nullable|integer|min:1',
            'ingredient_category_id' => 'nullable|integer|min:1',
            'amount'                 => 'required|numeric|min:0.01',
        ];
    }
}
