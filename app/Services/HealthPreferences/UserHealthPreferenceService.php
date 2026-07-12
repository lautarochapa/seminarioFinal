<?php

namespace App\Services\HealthPreferences;

use App\AuditLog;
use App\Exceptions\HealthPreferences\HealthPreferenceException;
use App\Repositories\HealthPreferences\HealthPreferenceRepository;
use App\Repositories\HealthPreferences\UserHealthPreferenceRepository;
use Illuminate\Support\Facades\DB;

class UserHealthPreferenceService
{
    private $catalog;
    private $relations;

    public function __construct(HealthPreferenceRepository $catalog, UserHealthPreferenceRepository $relations)
    {
        $this->catalog = $catalog;
        $this->relations = $relations;
    }

    public function list(array $type, $userId)
    {
        return $this->relations->listForUser($type, $userId);
    }

    public function create(array $type, $actorId, array $data, $ip, $userAgent)
    {
        $catalogId = $data[$type['relation_fk']];
        $this->catalog->findActiveOrFail($type, $catalogId);

        if ($this->relations->existsForUser($type, $actorId, $catalogId)) {
            throw new HealthPreferenceException('USER_HEALTH_PREFERENCE_ALREADY_EXISTS', 'La selección ya existe.', 409);
        }

        return DB::transaction(function () use ($type, $actorId, $data, $catalogId, $ip, $userAgent) {
            $payload = [
                'user_id' => $actorId,
                $type['relation_fk'] => $catalogId,
                'notes' => isset($data['notes']) && ! is_null($data['notes']) ? trim($data['notes']) : null,
                'created_at' => now(),
            ];

            foreach ($type['extra_relation_fields'] as $field) {
                $payload[$field] = $data[$field] ?? null;
            }

            $relation = $this->relations->create($type, $payload);
            $this->audit($type, $actorId, 'user.health-preference.created', $relation->id, null, $payload, $ip, $userAgent);

            return $relation;
        });
    }

    public function delete(array $type, $actorId, $id, $ip, $userAgent)
    {
        $relation = $this->relations->findForUserOrFail($type, $actorId, $id);

        return DB::transaction(function () use ($type, $actorId, $relation, $ip, $userAgent) {
            $old = $relation->toArray();
            $this->relations->delete($relation);
            $this->audit($type, $actorId, 'user.health-preference.deleted', $relation->id, $old, null, $ip, $userAgent);
        });
    }

    private function audit(array $type, $actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => $type['relation_table'],
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
