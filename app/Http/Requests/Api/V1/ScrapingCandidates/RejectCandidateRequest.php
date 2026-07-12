<?php

namespace App\Http\Requests\Api\V1\ScrapingCandidates;

use Illuminate\Foundation\Http\FormRequest;

class RejectCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
