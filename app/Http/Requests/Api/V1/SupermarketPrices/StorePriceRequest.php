<?php

namespace App\Http\Requests\Api\V1\SupermarketPrices;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'price'       => ['required', 'numeric', 'min:0.01'],
            'currency'    => ['required', 'string', 'in:ARS,USD,EUR'],
            'captured_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
