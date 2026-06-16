<?php

namespace App\Http\Requests\Api\V1\ScrapingAlerts;

use Illuminate\Foundation\Http\FormRequest;

class ResolveScrapingAlertRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'resolution_notes' => 'sometimes|nullable|string|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = ['resolution_notes'];
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'El campo '.$key.' no esta permitido.');
                }
            }
        });
    }
}
