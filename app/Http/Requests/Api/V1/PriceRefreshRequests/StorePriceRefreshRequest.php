<?php

namespace App\Http\Requests\Api\V1\PriceRefreshRequests;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceRefreshRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'reason' => 'nullable|string|max:1000',
            'supermarket_chain_id' => 'nullable|integer|min:1|exists:supermarket_chains,id',
            'supermarket_branch_id' => 'nullable|integer|min:1|exists:supermarket_branches,id',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach (['user_id', 'status', 'requested_at', 'processed_at', 'created_at', 'updated_at'] as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add($field, 'El campo '.$field.' no puede ser enviado.');
                }
            }
        });
    }

    protected function prepareForValidation()
    {
        if ($this->has('reason') && is_string($this->input('reason'))) {
            $reason = trim($this->input('reason'));
            $this->merge(['reason' => $reason === '' ? null : $reason]);
        }
    }
}
