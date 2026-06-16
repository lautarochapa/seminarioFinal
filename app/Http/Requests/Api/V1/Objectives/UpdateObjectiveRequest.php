<?php

namespace App\Http\Requests\Api\V1\Objectives;

use Illuminate\Foundation\Http\FormRequest;

class UpdateObjectiveRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'sometimes|string|max:80',
            'name' => 'sometimes|string|max:150',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }
}
