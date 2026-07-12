<?php

namespace App\Http\Requests\Api\V1\ThesisDocuments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThesisSectionRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'title'      => ['sometimes', 'string', 'max:180'],
            'content'    => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'parent_id'  => ['sometimes', 'nullable', 'integer'],
            'status'     => ['sometimes', 'string', 'in:draft,published,active'],
        ];
    }
}
