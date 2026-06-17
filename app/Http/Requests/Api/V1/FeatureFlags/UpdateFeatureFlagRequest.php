<?php

namespace App\Http\Requests\Api\V1\FeatureFlags;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => 'required',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_keys($this->all()) as $key) {
                if ($key !== 'enabled') {
                    $validator->errors()->add($key, 'El campo no esta permitido.');
                }
            }
        });
    }
}
