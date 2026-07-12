<?php

namespace App\Http\Requests\Api\V1\DemoScenarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDemoScenarioRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'name'        => ['required', 'string', 'max:160', Rule::unique('demo_scenarios', 'name')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:5000'],
            'route'       => ['nullable', 'string', 'max:255'],
            'status'      => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
