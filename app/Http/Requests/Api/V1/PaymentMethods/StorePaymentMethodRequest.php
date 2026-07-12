<?php

namespace App\Http\Requests\Api\V1\PaymentMethods;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'   => ['required', 'string', 'max:150'],
            'type'   => ['required', 'string', 'in:credit_card,debit_card,bank_account,digital_wallet,cash,other'],
            'issuer' => ['nullable', 'string', 'max:120'],
        ];
    }
}
