<?php

namespace App\Http\Requests\Api\V1\PaymentMethods;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserPaymentMethodRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'alias'             => ['nullable', 'string', 'max:120'],
        ];
    }
}
