<?php

namespace App\Http\Requests\Api\V1\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class ListPurchasesRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'date_from'              => 'nullable|date',
            'date_to'                => 'nullable|date|after_or_equal:date_from',
            'supermarket_branch_id'  => 'nullable|integer|min:1',
            'status'                 => 'nullable|string|in:confirmed,cancelled',
            'per_page'               => 'nullable|integer|min:1|max:100',
            'page'                   => 'nullable|integer|min:1',
        ];
    }
}
