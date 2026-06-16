<?php

namespace App\Services\Objectives;

use App\AuditLog;
use App\Exceptions\Objectives\ObjectivesException;
use App\Repositories\Objectives\ObjectiveRepository;
use App\Repositories\Objectives\UserObjectiveRepository;
use Illuminate\Support\Facades\DB;

class UserObjectiveService
{
    private $objectives;
    private $userObjectives;

    public function __construct(ObjectiveRepository $objectives, UserObjectiveRepository $userObjectives)
    {
        $this->objectives = $objectives;
        $this->userObjectives = $userObjectives;
    }

    public function list($userId)
    {
        return $this->userObjectives->listForUser($userId);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $this->objectives->findActiveOrFail($data['objective_id']);

        if ($this->userObjectives->activeExists($actorId, $data['objective_id'])) {
            throw new ObjectivesException('USER_OBJECTIVE_ALREADY_EXISTS', 'El usuario ya tiene este objetivo activo.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $assignment = $this->userObjectives->create([
                'user_id' => $actorId,
                'objective_id' => $data['objective_id'],
                'priority' => $data['priority'] ?? null,
                'target_value' => $data['target_value'] ?? null,
                'target_unit' => $data['target_unit'] ?? null,
                'target_date' => $data['target_date'] ?? null,
                'is_active' => true,
                'notes' => array_key_exists('notes', $data) && ! is_null($data['notes']) ? trim($data['notes']) : null,
            ]);

            $this->audit($actorId, 'user.objective.created', $assignment->id, null, $this->auditPayload($assignment), $ip, $userAgent);

            return $assignment;
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $assignment = $this->userObjectives->findForUserOrFail($actorId, $id);

        if (array_key_exists('objective_id', $data)) {
            $this->objectives->findActiveOrFail($data['objective_id']);

            if ($this->userObjectives->activeExists($actorId, $data['objective_id'], $assignment->id)) {
                throw new ObjectivesException('USER_OBJECTIVE_ALREADY_EXISTS', 'El usuario ya tiene este objetivo activo.', 409);
            }
        }

        return DB::transaction(function () use ($actorId, $assignment, $data, $ip, $userAgent) {
            $old = $this->auditPayload($assignment);

            if (array_key_exists('notes', $data) && ! is_null($data['notes'])) {
                $data['notes'] = trim($data['notes']);
            }

            $allowed = ['objective_id', 'priority', 'target_value', 'target_unit', 'target_date', 'notes'];
            $updated = $this->userObjectives->update($assignment, array_intersect_key($data, array_flip($allowed)));
            $new = $this->auditPayload($updated);

            if ($old != $new) {
                $this->audit($actorId, 'user.objective.updated', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $assignment = $this->userObjectives->findForUserOrFail($actorId, $id);

        return DB::transaction(function () use ($actorId, $assignment, $ip, $userAgent) {
            $old = $this->auditPayload($assignment);
            $updated = $this->userObjectives->update($assignment, ['is_active' => false]);

            $this->audit($actorId, 'user.objective.deleted', $updated->id, $old, $this->auditPayload($updated), $ip, $userAgent);
        });
    }

    private function auditPayload($assignment)
    {
        return [
            'objective_id' => $assignment->objective_id,
            'priority' => $assignment->priority,
            'target_value' => $assignment->target_value,
            'target_unit' => $assignment->target_unit,
            'target_date' => $assignment->target_date ? (string) $assignment->target_date : null,
            'is_active' => (bool) $assignment->is_active,
            'notes' => $assignment->notes,
        ];
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'user_objectives',
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
