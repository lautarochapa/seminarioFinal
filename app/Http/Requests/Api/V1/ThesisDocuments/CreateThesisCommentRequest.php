<?php

namespace App\Http\Requests\Api\V1\ThesisDocuments;

use Illuminate\Foundation\Http\FormRequest;

class CreateThesisCommentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'comment' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
