<?php

namespace App\Http\Requests\Api\V1\FoodTags;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFoodTagRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'sometimes|string|max:80',
            'name' => 'sometimes|string|max:150',
            'description' => 'sometimes|nullable|string',
            'type' => 'sometimes|nullable|string|max:60',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allowed = ['code', 'name', 'description', 'type', 'status'];
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'El campo '.$key.' no está permitido.');
                }
            }
        });
    }
}
