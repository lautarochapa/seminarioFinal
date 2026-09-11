<?php

namespace App\Http\Requests\Api\V1\RecipeImportCandidates;

use Illuminate\Foundation\Http\FormRequest;

class ApproveBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'candidate_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'candidate_ids.*' => ['integer', 'distinct'],
        ];
    }
}
