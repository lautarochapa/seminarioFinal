<?php

namespace App\Http\Requests\Api\V1\ScrapingCandidates;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['nullable', 'string', 'max:255'],
            'brand_id'    => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
        ];
    }
}
