<?php

namespace App\Http\Requests\Api\V1\RecipeScraping;

use Illuminate\Foundation\Http\FormRequest;

class CreateRecipeScrapingJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_pages' => 'sometimes|integer|min:1|max:50',
        ];
    }
}
