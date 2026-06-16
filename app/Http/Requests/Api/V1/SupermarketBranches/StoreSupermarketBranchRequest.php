<?php

namespace App\Http\Requests\Api\V1\SupermarketBranches;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupermarketBranchRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'supermarket_chain_id' => [
                'required', 'integer',
                Rule::exists('supermarket_chains', 'id')->where('status', 'active')->whereNull('deleted_at'),
            ],
            'city_id' => [
                'required', 'integer',
                Rule::exists('cities', 'id')->where('status', 'active'),
            ],
            'name'                => ['required', 'string', 'max:150'],
            'address'             => ['required', 'string', 'max:500'],
            'latitude'            => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'           => ['nullable', 'numeric', 'between:-180,180'],
            'phone'               => ['nullable', 'string', 'max:60'],
            'opening_hours'       => ['nullable', 'string'],
            'delivery_available'  => ['nullable', 'boolean'],
            'pickup_available'    => ['nullable', 'boolean'],
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
