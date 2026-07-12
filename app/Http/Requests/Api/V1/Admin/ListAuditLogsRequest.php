<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListAuditLogsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'page'        => 'integer|min:1',
            'per_page'    => 'integer|min:1|max:100',
            'search'      => 'string|max:200',
            'user_id'     => 'integer|min:1',
            'action'      => 'string|max:120',
            'resource'    => 'string|max:120',
            'resource_id' => 'string|max:120',
            'date_from'   => 'date_format:Y-m-d',
            'date_to'     => 'date_format:Y-m-d',
            'sort'        => 'nullable|string|max:40',
            'order'       => 'string|in:asc,desc',
        ];
    }
}
