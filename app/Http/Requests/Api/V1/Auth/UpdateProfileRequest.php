<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $userId = $this->user()->id;

        return [
            'name'       => 'sometimes|string|max:255',
            'lastname'   => 'sometimes|string|max:255',
            'username'   => 'sometimes|string|max:255|unique:users,username,' . $userId,
            'phone'      => 'sometimes|nullable|string|max:50',
            'avatar_url' => 'sometimes|nullable|string|max:2048',
        ];
    }
}
