<?php

namespace App\Http\Requests\Api\V1\RecipeFavoritesCooked;

use Illuminate\Foundation\Http\FormRequest;

class CookRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servings'         => 'required|integer|min:1',
            'family_group_id'  => 'sometimes|integer|min:1',
            'deduct_stock'     => 'sometimes|boolean',
            'idempotency_key'  => 'sometimes|string|max:120',
        ];
    }

    protected function prepareForValidation()
    {
        if (! $this->has('idempotency_key') && $this->header('X-Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('X-Idempotency-Key')]);
        }
    }
}
