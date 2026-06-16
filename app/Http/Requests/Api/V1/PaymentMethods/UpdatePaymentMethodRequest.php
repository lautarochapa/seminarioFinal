<?php

namespace App\Http\Requests\Api\V1\PaymentMethods;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'   => ['sometimes', 'string', 'max:150'],
            'type'   => ['sometimes', 'string', 'in:credit_card,debit_card,bank_account,digital_wallet,cash,other'],
            'issuer' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
