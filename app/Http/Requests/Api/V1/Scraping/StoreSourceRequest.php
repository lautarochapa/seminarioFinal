<?php

namespace App\Http\Requests\Api\V1\Scraping;

use Illuminate\Foundation\Http\FormRequest;

class StoreSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'      => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'name'      => ['required', 'string', 'max:150'],
            'type'      => ['nullable', 'string', 'in:web_scraper,api,feed'],
            'base_url'  => ['required', 'string', 'url', 'max:255'],
            'city_id'   => ['nullable', 'integer', 'exists:cities,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
