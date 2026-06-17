<?php

namespace App\Http\Requests\Api\V1\RecipeImportText;

use Illuminate\Foundation\Http\FormRequest;

class RecipeImportTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => 'required|string|min:20|max:20000',
        ];
    }
}
