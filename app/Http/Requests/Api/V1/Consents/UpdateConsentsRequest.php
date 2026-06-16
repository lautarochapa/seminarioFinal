<?php

namespace App\Http\Requests\Api\V1\Consents;

use App\Services\Consents\UserConsentService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConsentsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [];
        foreach (UserConsentService::CONSENT_TYPES as $type) {
            $rules[$type] = 'sometimes|boolean';
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = UserConsentService::CONSENT_TYPES;
            foreach (array_keys($this->all()) as $key) {
                if (!in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, "El campo {$key} no está permitido.");
                }
            }
        });
    }
}
