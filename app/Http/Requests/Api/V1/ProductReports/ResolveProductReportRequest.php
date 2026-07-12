<?php

namespace App\Http\Requests\Api\V1\ProductReports;

use App\ProductReport;
use Illuminate\Foundation\Http\FormRequest;

class ResolveProductReportRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'status' => ['required', 'string', 'in:' . implode(',', ProductReport::VALID_RESOLVE_STATUSES)],
        ];
    }
}
