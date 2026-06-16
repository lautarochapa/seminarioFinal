<?php

namespace App\Http\Requests\Api\V1\FamilyGroup;

use Illuminate\Foundation\Http\FormRequest;

class CreateFamilyGroupRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:150',
        ];
    }
}
