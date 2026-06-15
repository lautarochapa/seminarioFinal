<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'                  => 'required|string|max:255',
            'lastname'              => 'nullable|string|max:255',
            'email'                 => 'required|email|max:255',
            'password'              => 'required|string|min:8|confirmed',
            'status'                => 'nullable|string|in:active,inactive',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
    }
}
