<?php

namespace App\Http\Requests\Api\V1\HealthPreferences;

use Illuminate\Foundation\Http\FormRequest;

class HealthPreferenceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $isPost = $this->method() === 'POST';

        return [
            'code' => ($isPost ? 'required' : 'sometimes') . '|string|max:80',
            'name' => ($isPost ? 'required' : 'sometimes') . '|string|max:150',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }
}
