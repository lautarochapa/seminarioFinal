<?php

namespace App\Http\Requests\Api\V1\ThesisDocuments;

use Illuminate\Foundation\Http\FormRequest;

class CreateThesisSectionRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'title'      => ['required', 'string', 'max:180'],
            'content'    => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'parent_id'  => ['nullable', 'integer'],
            'status'     => ['nullable', 'string', 'in:draft,published,active'],
        ];
    }
}
