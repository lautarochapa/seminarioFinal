<?php

namespace App\Http\Requests\Api\V1\Units;

use App\Services\Units\UnitService;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:120',
            'type' => 'required|string|in:'.implode(',', UnitService::ALLOWED_TYPES),
            'symbol' => 'nullable|string|max:30',
            'status' => 'nullable|string|in:active,inactive',
        ];
    }
}
