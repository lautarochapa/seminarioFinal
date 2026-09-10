<?php

namespace App\Http\Requests\Api\V1\Scraping;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_id'             => ['required', 'integer', 'exists:scraping_sources,id'],
            'supermarket_chain_id'  => ['nullable', 'integer', 'exists:supermarket_chains,id'],
            'supermarket_branch_id' => ['nullable', 'integer', 'exists:supermarket_branches,id'],
            'max_pages'             => ['nullable', 'integer', 'between:1,50'],
            'max_products'          => ['nullable', 'integer', 'between:1,3000'],
            'delay_ms'              => ['nullable', 'integer', 'between:0,60000'],
            'dry_run'               => ['nullable', 'boolean'],
        ];
    }
}
