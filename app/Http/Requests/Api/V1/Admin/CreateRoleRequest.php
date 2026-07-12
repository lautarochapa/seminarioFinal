<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code'        => 'required|string|max:100|regex:/^[a-z0-9_]+$/',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }
}
