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
            'servings'         => 'required|integer|min:1|max:100',
            'family_group_id'  => 'required_if:deduct_stock,true|nullable|integer|min:1',
            'deduct_stock'     => 'sometimes|boolean',
            'idempotency_key'  => 'sometimes|string|max:120',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('deduct_stock')) {
            $value = $this->input('deduct_stock');
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($normalized !== null) {
                $this->merge(['deduct_stock' => $normalized]);
            }
        }
        if (! $this->has('idempotency_key') && $this->header('X-Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('X-Idempotency-Key')]);
        }
    }

    public function messages(): array
    {
        return [
            'servings.required' => 'Indicá una cantidad válida de porciones.',
            'servings.integer' => 'Indicá una cantidad válida de porciones.',
            'servings.min' => 'Indicá una cantidad válida de porciones.',
            'servings.max' => 'La cantidad máxima es de 100 porciones.',
            'family_group_id.required_if' => 'Seleccioná el grupo familiar del que querés descontar los ingredientes.',
            'family_group_id.integer' => 'Seleccioná un grupo familiar válido.',
            'deduct_stock.boolean' => 'El valor de descuento de stock no es válido.',
        ];
    }
}
