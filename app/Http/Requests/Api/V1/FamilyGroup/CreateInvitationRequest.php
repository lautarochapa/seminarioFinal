<?php

namespace App\Http\Requests\Api\V1\FamilyGroup;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvitationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'email' => 'required|email|max:255',
            'role'  => 'required|string|in:admin,member',
        ];
    }
}
