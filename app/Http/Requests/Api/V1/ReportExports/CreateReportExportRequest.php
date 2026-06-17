<?php

namespace App\Http\Requests\Api\V1\ReportExports;

use Illuminate\Foundation\Http\FormRequest;

class CreateReportExportRequest extends FormRequest
{
    const VALID_REPORT_TYPES = [
        'stock', 'stock-value', 'expiring-products', 'waste',
        'purchases', 'budget', 'budget-vs-actual', 'recipes-cooked', 'nutrition-estimate',
    ];

    const VALID_FORMATS = ['json', 'csv'];

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'report_type' => ['required', 'string', 'in:' . implode(',', self::VALID_REPORT_TYPES)],
            'format'      => ['required', 'string', 'in:' . implode(',', self::VALID_FORMATS)],
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date', 'after_or_equal:date_from'],
            'days'        => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
