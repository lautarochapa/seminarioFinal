<?php

namespace App\Http\Requests\Api\V1\Promotions;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $type          = $this->input('discount_type');
        $discountRules = ['sometimes', 'nullable', 'numeric', 'min:0'];

        if ($type === 'percentage') {
            $discountRules[] = 'max:100';
        } elseif ($type === 'fixed_amount') {
            $discountRules = ['sometimes', 'nullable', 'numeric', 'min:0.01'];
        }

        return [
            'name'                    => ['sometimes', 'string', 'max:150'],
            'description'             => ['sometimes', 'nullable', 'string'],
            'discount_type'           => ['sometimes', 'nullable', 'string', 'in:percentage,fixed_amount,buy_x_pay_y,payment_method,day_discount'],
            'discount_value'          => $discountRules,
            'valid_from'              => ['sometimes', 'nullable', 'date'],
            'valid_to'                => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'day_of_week'             => ['sometimes', 'nullable', 'integer', 'between:0,6'],
            'requires_payment_method' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
