<?php

namespace App\Http\Requests\Api\V1\Scraping;

use App\SupermarketChain;
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
            // Un job de scraping de productos sin cadena de supermercado
            // asociada nunca puede aprobarse despues (ScrapingCandidateService
            // ::doApprove() exige supermarket_chain_id para crear el
            // SupermarketProduct): se rechaza aca, antes de crear el job, en
            // vez de dejar que las candidatas queden atrapadas sin poder
            // aprobarse recien al final de la revision.
            'supermarket_chain_id'  => ['required', 'integer', 'exists:supermarket_chains,id'],
            'supermarket_branch_id' => ['nullable', 'integer', 'exists:supermarket_branches,id'],
            'max_pages'             => ['nullable', 'integer', 'between:1,50'],
            'max_products'          => ['nullable', 'integer', 'between:1,3000'],
            'delay_ms'              => ['nullable', 'integer', 'between:0,60000'],
            'dry_run'               => ['nullable', 'boolean'],
            'search_term'           => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $chainId = $this->input('supermarket_chain_id');
            if (!$chainId) {
                return;
            }

            $isActive = SupermarketChain::where('id', $chainId)->where('status', 'active')->exists();
            if (!$isActive) {
                $validator->errors()->add('supermarket_chain_id', 'La cadena de supermercado no esta activa.');
            }
        });
    }
}
