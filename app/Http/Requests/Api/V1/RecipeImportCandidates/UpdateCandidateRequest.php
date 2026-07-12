<?php

namespace App\Http\Requests\Api\V1\RecipeImportCandidates;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'raw_title'            => 'sometimes|string|max:255',
            'raw_description'      => 'sometimes|nullable|string|max:5000',
            'raw_image_url'        => 'sometimes|nullable|url|max:500',
            'raw_ingredients_json' => 'sometimes|nullable|array',
            'raw_steps_json'       => 'sometimes|nullable|array',
        ];
    }
}
