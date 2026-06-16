<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|string',
            'remember' => 'sometimes|boolean',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
    }
}
