<?php

namespace App\Http\Requests\Api\V1\ThesisDocuments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThesisDocumentRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'title'       => ['sometimes', 'string', 'max:180'],
            'slug'        => ['sometimes', 'string', 'max:200', 'unique:thesis_documents,slug,' . $id, 'regex:/^[a-z0-9\-]+$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status'      => ['sometimes', 'string', 'in:draft,published,active,archived'],
        ];
    }
}
