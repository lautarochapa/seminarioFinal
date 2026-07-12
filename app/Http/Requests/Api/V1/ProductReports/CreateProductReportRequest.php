<?php

namespace App\Http\Requests\Api\V1\ProductReports;

use App\ProductReport;
use Illuminate\Foundation\Http\FormRequest;

class CreateProductReportRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type'        => ['required', 'string', 'in:' . implode(',', ProductReport::VALID_TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
