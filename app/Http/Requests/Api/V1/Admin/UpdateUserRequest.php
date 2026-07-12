<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'       => 'sometimes|string|max:255',
            'lastname'   => 'sometimes|nullable|string|max:255',
            'username'   => 'sometimes|nullable|string|max:255',
            'phone'      => 'sometimes|nullable|string|max:50',
            'avatar_url' => 'sometimes|nullable|string|max:2048',
            'status'     => 'sometimes|string|in:active,inactive,banned',
        ];
    }
}
