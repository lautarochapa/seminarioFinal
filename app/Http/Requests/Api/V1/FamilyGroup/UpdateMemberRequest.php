<?php

namespace App\Http\Requests\Api\V1\FamilyGroup;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'role'   => 'sometimes|string|in:owner,admin,member',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }
}
