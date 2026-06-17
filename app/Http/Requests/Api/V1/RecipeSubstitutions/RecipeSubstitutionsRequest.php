<?php

namespace App\Http\Requests\Api\V1\RecipeSubstitutions;

use Illuminate\Foundation\Http\FormRequest;

class RecipeSubstitutionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'family_group_id'          => 'sometimes|integer|min:1',
            'exclude_ingredient_ids'   => 'sometimes|array',
            'exclude_ingredient_ids.*' => 'integer|min:1',
        ];
    }
}
