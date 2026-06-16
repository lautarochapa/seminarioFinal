<?php

namespace App\Http\Requests\Api\V1\Professional;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLinkRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'can_view_profile'    => 'sometimes|boolean',
            'can_view_stock'      => 'sometimes|boolean',
            'can_view_meal_plans' => 'sometimes|boolean',
            'can_edit_meal_plans' => 'sometimes|boolean',
            'can_view_reports'    => 'sometimes|boolean',
        ];
    }
}
