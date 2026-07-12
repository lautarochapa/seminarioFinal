<?php

namespace App\Http\Requests\Api\V1\Cities;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'      => ['sometimes', 'string', 'max:150'],
            'province'  => ['sometimes', 'nullable', 'string', 'max:150'],
            'country'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'latitude'  => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }
        if ($this->has('province')) {
            $this->merge(['province' => trim($this->province)]);
        }
    }
}
