<?php

namespace App\Http\Requests\Api\V1\FamilyGroup;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'default_budget_mode'          => 'sometimes|nullable|string|max:50',
            'default_shopping_mode'        => 'sometimes|nullable|string|max:50',
            'default_recipe_priority_mode' => 'sometimes|nullable|string|max:50',
            'allow_auto_stock_discount'    => 'sometimes|boolean',
        ];
    }
}
