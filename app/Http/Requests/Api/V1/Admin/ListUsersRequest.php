<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListUsersRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'page'            => 'integer|min:1',
            'per_page'        => 'integer|min:1|max:100',
            'search'          => 'string|max:100',
            'status'          => 'string|in:active,inactive,banned',
            'include_deleted' => 'boolean',
            'sort'            => 'string|in:name,email,created_at,last_login_at',
            'order'           => 'string|in:asc,desc',
        ];
    }
}
