<?php

namespace App\Http\Requests\Api\V1\FamilyGroup;

use Illuminate\Foundation\Http\FormRequest;

class AddMemberRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => 'required|integer|min:1',
            'role'    => 'required|string|in:admin,member',
        ];
    }
}
