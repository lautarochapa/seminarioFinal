<?php

namespace App\Services\FeatureFlags;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\FeatureFlag;
use App\Repositories\FeatureFlags\FeatureFlagRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeatureFlagService
{
    private $flags;

    public function __construct(FeatureFlagRepository $flags)
    {
        $this->flags = $flags;
    }

    public function list(array $filters = [])
    {
        return $this->flags->all($filters);
    }

    public function enabled($key)
    {
        return Cache::rememberForever($this->cacheKey($key), function () use ($key) {
            $flag = $this->flags->findByKey($key);

            return $flag ? (bool) $flag->enabled : false;
        });
    }

    public function update($actorId, $key, array $data, $ip, $userAgent)
    {
        $flag = $this->flags->findByKey($key);

        if (!$flag) {
            throw new IngredientException('FEATURE_FLAG_NOT_FOUND', 'El feature flag solicitado no existe.', 404);
        }

        if (!is_bool($data['enabled'])) {
            throw new IngredientException('FEATURE_FLAG_INVALID_VALUE', 'El valor del feature flag debe ser booleano.', 422);
        }

        return DB::transaction(function () use ($actorId, $flag, $data, $ip, $userAgent) {
            $old = $this->auditPayload($flag);
            $updated = $this->flags->update($flag, ['enabled' => $data['enabled']]);
            Cache::forget($this->cacheKey($updated->key));
            $new = $this->auditPayload($updated);

            if ($old != $new) {
                AuditLog::create([
                    'user_id' => $actorId,
                    'action' => 'feature-flag.updated',
                    'entity_name' => 'feature_flags',
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

    private function auditPayload(FeatureFlag $flag)
    {
        return [
            'key' => $flag->key,
            'name' => $flag->name,
            'description' => $flag->description,
            'enabled' => (bool) $flag->enabled,
        ];
    }

    private function cacheKey($key)
    {
        return 'feature_flags.'.$key;
    }
}
