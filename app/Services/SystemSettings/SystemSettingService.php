<?php

namespace App\Services\SystemSettings;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\Repositories\SystemSettings\SystemSettingRepository;
use App\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SystemSettingService
{
    private $settings;

    public function __construct(SystemSettingRepository $settings)
    {
        $this->settings = $settings;
    }

    public function list(array $filters)
    {
        return $this->settings->paginate($filters);
    }

    public function update($actorId, $key, array $data, $ip, $userAgent)
    {
        $setting = $this->settings->findByKey($key);

        if (!$setting) {
            throw new IngredientException('SYSTEM_SETTING_NOT_FOUND', 'La configuracion solicitada no existe.', 404);
        }

        $value = $this->normalizeValue($setting, $data['value']);

        return DB::transaction(function () use ($actorId, $setting, $value, $ip, $userAgent) {
            $old = $this->auditPayload($setting);
            $updated = $this->settings->update($setting, ['value' => $value]);
            $new = $this->auditPayload($updated);

            if ($old != $new) {
                AuditLog::create([
                    'user_id' => $actorId,
                    'action' => 'system-setting.updated',
                    'entity_name' => 'system_settings',
                    'entity_id' => (string) $updated->id,
                    'old_values' => $old,
                    'new_values' => $new,
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                ]);
            }

            return $updated;
        });
    }

    public function displayValue(SystemSetting $setting)
    {
        if ($this->isSensitive($setting->key)) {
            return '[REDACTED]';
        }

        return $this->castValue($setting->value, $setting->type);
    }

    private function normalizeValue(SystemSetting $setting, $value)
    {
        switch ($setting->type) {
            case 'string':
                if (is_array($value) || is_object($value)) {
                    $this->invalidValue();
                }

                return trim((string) $value);

            case 'integer':
                if (is_int($value)) {
                    return (string) $value;
                }

                if (is_string($value) && preg_match('/^-?[0-9]+$/', trim($value))) {
                    return trim($value);
                }

                $this->invalidValue();
                break;

            case 'boolean':
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                if ($value === 1 || $value === 0 || $value === '1' || $value === '0') {
                    return ((int) $value) === 1 ? 'true' : 'false';
                }

                $this->invalidValue();
                break;

            case 'json':
                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }

                if (is_string($value)) {
                    json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $value;
                    }
                }

                $this->invalidValue();
                break;

            case 'date':
                if (!is_string($value)) {
                    $this->invalidValue();
                }

                try {
                    return Carbon::parse($value)->toDateString();
                } catch (\Exception $e) {
                    $this->invalidValue();
                }
                break;

            default:
                throw new IngredientException('SYSTEM_SETTING_INVALID_TYPE', 'El tipo de configuracion no esta soportado.', 422);
        }

        $this->invalidValue();
    }

    private function castValue($value, $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                $decoded = json_decode($value, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            default:
                return $value;
        }
    }

    private function auditPayload(SystemSetting $setting)
    {
        return [
            'key' => $setting->key,
            'value' => $this->isSensitive($setting->key) ? '[REDACTED]' : $this->castValue($setting->value, $setting->type),
            'type' => $setting->type,
            'description' => $setting->description,
            'is_public' => (bool) $setting->is_public,
        ];
    }

    private function isSensitive($key)
    {
        return preg_match('/(password|token|secret|api_key|client_secret|credential|authorization|cookie|session)/i', (string) $key) === 1;
    }

    private function invalidValue()
    {
        throw new IngredientException('SYSTEM_SETTING_INVALID_VALUE', 'El valor no es valido para el tipo de configuracion.', 422);
    }
}
