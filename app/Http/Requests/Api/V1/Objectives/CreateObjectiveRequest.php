<?php

namespace App\Http\Requests\Api\V1\Objectives;

use Illuminate\Foundation\Http\FormRequest;

class CreateObjectiveRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'required|string|max:80',
            'name' => 'required|string|max:150',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }
}
