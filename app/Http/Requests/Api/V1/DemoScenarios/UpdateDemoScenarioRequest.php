<?php

namespace App\Http\Requests\Api\V1\DemoScenarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDemoScenarioRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        $id = (int) $this->route('id');

        return [
            'name'        => ['sometimes', 'string', 'max:160', Rule::unique('demo_scenarios', 'name')->ignore($id)->whereNull('deleted_at')],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'route'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'status'      => ['sometimes', 'string', 'in:active,inactive'],
        ];
    }
}
