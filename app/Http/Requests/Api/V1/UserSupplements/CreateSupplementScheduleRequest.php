<?php

namespace App\Http\Requests\Api\V1\UserSupplements;

use Illuminate\Foundation\Http\FormRequest;

class CreateSupplementScheduleRequest extends FormRequest
{
    public function authorize() { return $this->user() !== null; }

    public function rules()
    {
        return [
            'time_of_day'      => 'nullable|date_format:H:i',
            'days_of_week'     => 'nullable|array',
            'days_of_week.*'   => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'reminder_enabled' => 'boolean',
        ];
    }
}
