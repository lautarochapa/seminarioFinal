<?php

namespace App\Services\HealthPreferences;

use App\AuditLog;
use App\Exceptions\HealthPreferences\HealthPreferenceException;
use App\Repositories\HealthPreferences\HealthPreferenceRepository;
use Illuminate\Support\Facades\DB;

class HealthPreferenceAdminService
{
    private $repo;

    public function __construct(HealthPreferenceRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(array $type, array $filters)
    {
        return $this->repo->paginate($type, $filters);
    }

    public function catalog(array $type)
    {
        return $this->repo->activeCatalog($type);
    }

    public function show(array $type, $id)
    {
        return $this->repo->findOrFail($type, $id);
    }

    public function create(array $type, $actorId, array $data, $ip, $userAgent)
    {
        $data['code'] = $this->normalizeCode($data['code']);

        if ($this->repo->codeExists($type, $data['code'])) {
            throw new HealthPreferenceException('HEALTH_PREFERENCE_CODE_ALREADY_EXISTS', 'El código ya existe.', 409);
        }

        return DB::transaction(function () use ($type, $actorId, $data, $ip, $userAgent) {
            $model = $type['model'];
            $item = $model::create([
                'code' => $data['code'],
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'status' => $data['status'] ?? 'active',
            ]);

            $this->audit($type, $actorId, 'health-preference.admin.create', $item->id, null, $item->only(['code', 'name', 'description', 'status']), $ip, $userAgent);

            return $item;
        });
    }

    public function update(array $type, $actorId, $id, array $data, $ip, $userAgent)
    {
        $item = $this->repo->findOrFail($type, $id);

        if (array_key_exists('code', $data)) {
            $data['code'] = $this->normalizeCode($data['code']);

            if ($this->repo->codeExists($type, $data['code'], $item->id)) {
                throw new HealthPreferenceException('HEALTH_PREFERENCE_CODE_ALREADY_EXISTS', 'El código ya existe.', 409);
            }
        }

        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $data) && ! is_null($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        return DB::transaction(function () use ($type, $actorId, $item, $data, $ip, $userAgent) {
            $old = $item->only(['code', 'name', 'description', 'status']);
            $item->fill(array_intersect_key($data, array_flip(['code', 'name', 'description', 'status'])));
            $item->save();
            $new = $item->fresh()->only(['code', 'name', 'description', 'status']);

            if ($old != $new) {
                $this->audit($type, $actorId, 'health-preference.admin.update', $item->id, $old, $new, $ip, $userAgent);
            }

            return $item->fresh();
        });
    }

    public function delete(array $type, $actorId, $id, $ip, $userAgent)
    {
        $item = $this->repo->findOrFail($type, $id);

        return DB::transaction(function () use ($type, $actorId, $item, $ip, $userAgent) {
            if ($type['uses_soft_delete']) {
                $item->delete();
                $deleted = $this->repo->findWithTrashedOrFail($type, $item->id);
                $new = ['deleted_at' => (string) $deleted->deleted_at];
            } else {
                $item->status = 'inactive';
                $item->save();
                $deleted = $item->fresh();
                $new = ['status' => 'inactive'];
            }

            $this->audit($type, $actorId, 'health-preference.admin.delete', $item->id, null, $new, $ip, $userAgent);

            return $deleted;
        });
    }

    public function restore(array $type, $actorId, $id, $ip, $userAgent)
    {
        $item = $this->repo->findWithTrashedOrFail($type, $id);

        return DB::transaction(function () use ($type, $actorId, $item, $ip, $userAgent) {
            if ($type['uses_soft_delete']) {
                $old = ['deleted_at' => (string) $item->deleted_at];
                $item->restore();
                $restored = $item->fresh();
                $new = ['deleted_at' => null];
            } else {
                $old = ['status' => $item->status];
                $item->status = 'active';
                $item->save();
                $restored = $item->fresh();
                $new = ['status' => 'active'];
            }

            $this->audit($type, $actorId, 'health-preference.admin.restore', $restored->id, $old, $new, $ip, $userAgent);

            return $restored;
        });
    }

    private function normalizeCode($code)
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9]+/', '_', $code);

        return trim($code, '_');
    }

    private function audit(array $type, $actorId, $action, $entityId, $old, $new, $ip, $userAgent)
    {
        AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'entity_name' => $type['resource'],
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
