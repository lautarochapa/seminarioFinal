<?php

namespace App\Http\Requests\Api\V1\RecipeShoppingList;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GenerateRecipeShoppingListRequest extends FormRequest
{
    private const ALLOWED = ['servings', 'shopping_list_id', 'supermarket_branch_id', 'supermarket_chain_id'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servings'               => 'nullable|integer|min:1|max:1000',
            'shopping_list_id'       => 'nullable|integer|min:1',
            'supermarket_branch_id'  => 'nullable|integer|min:1',
            'supermarket_chain_id'   => 'nullable|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, self::ALLOWED, true)) {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
