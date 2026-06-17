<?php

namespace App\Http\Requests\Api\V1\Notifications;

use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize() { return $this->user() !== null; }

    public function rules()
    {
        $validTypes = implode(',', NotificationService::VALID_TYPES);
        $validFreqs = implode(',', NotificationService::VALID_FREQUENCIES);

        return [
            'preferences'                        => 'required|array|min:1',
            'preferences.*.notification_type'    => 'required|string|in:' . $validTypes,
            'preferences.*.app_enabled'          => 'sometimes|boolean',
            'preferences.*.email_enabled'        => 'sometimes|boolean',
            'preferences.*.push_enabled'         => 'sometimes|boolean',
            'preferences.*.frequency'            => 'sometimes|string|in:' . $validFreqs,
        ];
    }
}
