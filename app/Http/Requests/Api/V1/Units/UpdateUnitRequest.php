<?php

namespace App\Http\Requests\Api\V1\Units;

use App\Services\Units\UnitService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'sometimes|string|max:50',
            'name' => 'sometimes|string|max:120',
            'type' => 'sometimes|string|in:'.implode(',', UnitService::ALLOWED_TYPES),
            'symbol' => 'sometimes|nullable|string|max:30',
            'status' => 'sometimes|string|in:active,inactive',
        ];
    }
}
