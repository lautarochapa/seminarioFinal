<?php

namespace App\Services\Objectives;

use App\AuditLog;
use App\Exceptions\Objectives\ObjectivesException;
use App\Repositories\Objectives\ObjectiveRepository;
use Illuminate\Support\Facades\DB;

class ObjectiveAdminService
{
    private $objectives;

    public function __construct(ObjectiveRepository $objectives)
    {
        $this->objectives = $objectives;
    }

    public function list(array $filters)
    {
        return $this->objectives->paginate($filters);
    }

    public function catalog()
    {
        return $this->objectives->activeCatalog();
    }

    public function show($id)
    {
        return $this->objectives->findOrFail($id);
    }

    public function create($actorId, array $data, $ip, $userAgent)
    {
        $data['code'] = $this->normalizeCode($data['code']);

        if ($this->objectives->existsByCode($data['code'])) {
            throw new ObjectivesException('OBJECTIVE_CODE_ALREADY_EXISTS', 'El código de objetivo ya existe.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $userAgent) {
            $objective = $this->objectives->create([
                'code' => $data['code'],
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'status' => $data['status'] ?? 'active',
            ]);

            $this->audit($actorId, 'objective.admin.create', $objective->id, null, $objective->only(['code', 'name', 'description', 'status']), $ip, $userAgent);

            return $objective;
        });
    }

    public function update($actorId, $id, array $data, $ip, $userAgent)
    {
        $objective = $this->objectives->findOrFail($id);

        if (array_key_exists('code', $data)) {
            $data['code'] = $this->normalizeCode($data['code']);

            if ($this->objectives->existsByCode($data['code'], $objective->id)) {
                throw new ObjectivesException('OBJECTIVE_CODE_ALREADY_EXISTS', 'El código de objetivo ya existe.', 409);
            }
        }

        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $data) && ! is_null($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        return DB::transaction(function () use ($actorId, $objective, $data, $ip, $userAgent) {
            $old = $objective->only(['code', 'name', 'description', 'status']);
            $updated = $this->objectives->update($objective, array_intersect_key($data, array_flip(['code', 'name', 'description', 'status'])));
            $new = $updated->only(['code', 'name', 'description', 'status']);

            if ($old != $new) {
                $this->audit($actorId, 'objective.admin.update', $updated->id, $old, $new, $ip, $userAgent);
            }

            return $updated;
        });
    }

    public function delete($actorId, $id, $ip, $userAgent)
    {
        $objective = $this->objectives->findOrFail($id);

        return DB::transaction(function () use ($actorId, $objective, $ip, $userAgent) {
            $objective->delete();
            $deleted = $this->objectives->findWithTrashedOrFail($objective->id);

            $this->audit($actorId, 'objective.admin.delete', $objective->id, ['deleted_at' => null], ['deleted_at' => (string) $deleted->deleted_at], $ip, $userAgent);

            return $deleted;
        });
    }

    public function restore($actorId, $id, $ip, $userAgent)
    {
        $objective = $this->objectives->findWithTrashedOrFail($id);

        if (! $objective->trashed()) {
            throw new ObjectivesException('RESOURCE_NOT_DELETED', 'El recurso no está eliminado.', 409);
        }

        return DB::transaction(function () use ($actorId, $objective, $ip, $userAgent) {
            $old = ['deleted_at' => (string) $objective->deleted_at];
            $objective->restore();
            $restored = $objective->fresh();

            $this->audit($actorId, 'objective.admin.restore', $objective->id, $old, ['deleted_at' => null], $ip, $userAgent);

            return $restored;
        });
    }

    private function normalizeCode($code)
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9]+/', '_', $code);

        return trim($code, '_');
    }

    private function audit($actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => 'objectives',
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
