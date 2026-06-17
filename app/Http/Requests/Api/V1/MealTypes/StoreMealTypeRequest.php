<?php

namespace App\Http\Requests\Api\V1\MealTypes;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealTypeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'       => 'required|string|max:80|regex:/^[a-z0-9_]+$/',
            'name'       => 'required|string|max:120',
            'sort_order' => 'sometimes|integer|min:0|max:9999',
            'status'     => 'sometimes|in:active,inactive',
        ];
    }
}
