<?php

namespace App\Http\Requests\Api\V1\ThesisDocuments;

use Illuminate\Foundation\Http\FormRequest;

class CreateThesisDocumentRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'title'       => ['required', 'string', 'max:180'],
            'slug'        => ['nullable', 'string', 'max:200', 'unique:thesis_documents,slug', 'regex:/^[a-z0-9\-]+$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status'      => ['nullable', 'string', 'in:draft,published,active,archived'],
        ];
    }
}
