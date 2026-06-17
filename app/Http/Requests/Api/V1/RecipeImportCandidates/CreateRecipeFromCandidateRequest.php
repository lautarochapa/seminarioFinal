<?php

namespace App\Http\Requests\Api\V1\RecipeImportCandidates;

use Illuminate\Foundation\Http\FormRequest;

class CreateRecipeFromCandidateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'is_public' => 'sometimes|boolean',
            'notes'     => 'sometimes|nullable|string|max:1000',
        ];
    }
}
