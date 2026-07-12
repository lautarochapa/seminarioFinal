<?php

namespace App\Http\Requests\Api\V1\ScrapingCandidates;

use Illuminate\Foundation\Http\FormRequest;

class AssignIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
        ];
    }
}
