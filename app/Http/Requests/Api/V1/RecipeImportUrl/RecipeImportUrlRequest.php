<?php

namespace App\Http\Requests\Api\V1\RecipeImportUrl;

use Illuminate\Foundation\Http\FormRequest;

class RecipeImportUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => 'required|string|max:2048',
        ];
    }
}
