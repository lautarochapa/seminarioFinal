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
            'max_pages'    => 'sometimes|integer|min:1|max:50',
            'max_recipes'  => 'sometimes|integer|min:1|max:200',
            'delay_ms'     => 'sometimes|integer|min:0|max:60000',
            'search_term'  => 'nullable|string|max:100',
        ];
    }
}
