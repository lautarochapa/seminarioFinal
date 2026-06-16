<?php

namespace App\Http\Requests\Api\V1\SupermarketBranches;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupermarketBranchRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'supermarket_chain_id' => [
                'sometimes', 'integer',
                Rule::exists('supermarket_chains', 'id')->where('status', 'active')->whereNull('deleted_at'),
            ],
            'city_id' => [
                'sometimes', 'integer',
                Rule::exists('cities', 'id')->where('status', 'active'),
            ],
            'name'               => ['sometimes', 'string', 'max:150'],
            'address'            => ['sometimes', 'string', 'max:500'],
            'latitude'           => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude'          => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'phone'              => ['sometimes', 'nullable', 'string', 'max:60'],
            'opening_hours'      => ['sometimes', 'nullable', 'string'],
            'delivery_available' => ['sometimes', 'boolean'],
            'pickup_available'   => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }
        if ($this->has('address')) {
            $this->merge(['address' => trim($this->address)]);
        }
    }
}
