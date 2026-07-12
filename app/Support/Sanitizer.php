<?php

namespace App\Support;

class Sanitizer
{
    const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', 'access_token',
        'refresh_token', 'authorization', 'cookie', 'session',
        'secret', 'api_key', 'client_secret',
    ];

    public static function redact($data)
    {
        if (is_null($data)) {
            return null;
        }

        if (!is_array($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = self::redact($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
