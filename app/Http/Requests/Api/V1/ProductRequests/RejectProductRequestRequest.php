<?php

namespace App\Http\Requests\Api\V1\ProductRequests;

use Illuminate\Foundation\Http\FormRequest;

class RejectProductRequestRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'review_notes' => 'nullable|string|max:1000',
        ];
    }
}
