<?php

namespace App\Http\Requests\Api\V1\ScrapingCandidates;

use Illuminate\Foundation\Http\FormRequest;

class MatchProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ];
    }
}
