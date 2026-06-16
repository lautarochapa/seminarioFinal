<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListLoginLogsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'page'      => 'integer|min:1',
            'per_page'  => 'integer|min:1|max:100',
            'search'    => 'string|max:200',
            'user_id'   => 'integer|min:1',
            'email'     => 'string|max:255',
            'success'   => 'boolean',
            'ip'        => 'string|max:45',
            'date_from' => 'date_format:Y-m-d',
            'date_to'   => 'date_format:Y-m-d',
            'sort'      => 'string|in:id,created_at,user_id,email,success',
            'order'     => 'string|in:asc,desc',
        ];
    }
}
