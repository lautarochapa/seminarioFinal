<?php

namespace App\Http\Requests\Api\V1\FamilyGroup;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFamilyGroupRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'    => 'sometimes|string|max:150',
            'status'  => 'sometimes|string|in:active,inactive',
            'city_id' => 'sometimes|nullable|integer|exists:cities,id',
        ];
    }
}
