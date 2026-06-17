<?php

namespace App\Http\Requests\Api\V1\RecipeImportCandidates;

use Illuminate\Foundation\Http\FormRequest;

class RejectCandidateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reason' => 'sometimes|nullable|string|max:1000',
        ];
    }
}
