<?php

namespace App\Http\Requests\Api\V1\Promotions;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $type          = $this->input('discount_type');
        $discountRules = ['nullable', 'numeric', 'min:0'];

        if ($type === 'percentage') {
            $discountRules[] = 'max:100';
        } elseif ($type === 'fixed_amount') {
            $discountRules = ['nullable', 'numeric', 'min:0.01'];
        }

        return [
            'supermarket_chain_id'    => ['required', 'integer', 'exists:supermarket_chains,id'],
            'supermarket_branch_id'   => ['nullable', 'integer', 'exists:supermarket_branches,id'],
            'name'                    => ['required', 'string', 'max:150'],
            'description'             => ['nullable', 'string'],
            'discount_type'           => ['nullable', 'string', 'in:percentage,fixed_amount,buy_x_pay_y,payment_method,day_discount'],
            'discount_value'          => $discountRules,
            'valid_from'              => ['nullable', 'date'],
            'valid_to'                => ['nullable', 'date', 'after_or_equal:valid_from'],
            'day_of_week'             => ['nullable', 'integer', 'between:0,6'],
            'requires_payment_method' => ['nullable', 'boolean'],
        ];
    }
}
