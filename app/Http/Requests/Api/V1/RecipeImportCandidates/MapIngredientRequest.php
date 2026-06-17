<?php

namespace App\Http\Requests\Api\V1\RecipeImportCandidates;

use Illuminate\Foundation\Http\FormRequest;

class MapIngredientRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'ingredient_index' => 'required|integer|min:0',
            'ingredient_id'    => 'required|integer|min:1',
            'unit_id'          => 'required|integer|min:1',
            'quantity'         => 'sometimes|nullable|numeric|min:0',
            'is_optional'      => 'sometimes|boolean',
            'notes'            => 'sometimes|nullable|string|max:300',
        ];
    }
}
