<?php

namespace App\Services\Admin;

use App\AuditLog;
use App\Exceptions\Rbac\RbacException;
use App\Repositories\Admin\RoleRepository;
use App\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RoleAdminService
{
    private $roleRepo;
    private $auditService;

    public function __construct(RoleRepository $roleRepo, AuditAdminService $auditService)
    {
        $this->roleRepo     = $roleRepo;
        $this->auditService = $auditService;
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->roleRepo->paginate($filters);
    }

    public function show(int $id): Role
    {
        return $this->roleRepo->findOrFail($id);
    }

    public function create(int $actorId, array $data, string $ip, string $ua): Role
    {
        if (Role::where('code', $data['code'])->exists()) {
            throw new RbacException('ROLE_CODE_ALREADY_EXISTS', 'El código de rol ya existe.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $ip, $ua) {
            $role = $this->roleRepo->create([
                'code'        => $data['code'],
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'status'      => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'role.admin.create',
                'entity_name' => 'roles',
                'entity_id'   => $role->id,
                'old_values'  => null,
                'new_values'  => ['code' => $role->code, 'name' => $role->name],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $role;
        });
    }

    public function update(int $actorId, int $id, array $data, string $ip, string $ua): Role
    {
        $role = $this->roleRepo->findOrFail($id);

        return DB::transaction(function () use ($actorId, $role, $data, $ip, $ua) {
            $allowed  = ['name', 'description'];
            $filtered = array_intersect_key($data, array_flip($allowed));
            $old      = $role->only($allowed);

            $updated = $this->roleRepo->update($role, $filtered);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'role.admin.update',
                'entity_name' => 'roles',
                'entity_id'   => $role->id,
                'old_values'  => $old,
                'new_values'  => $filtered,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $updated;
        });
    }

    public function delete(int $actorId, int $id, string $ip, string $ua): Role
    {
        $role = $this->roleRepo->findOrFail($id);

        if ($role->status === 'inactive') {
            throw new RbacException('RESOURCE_ALREADY_DELETED', 'El rol ya está inactivo.', 409);
        }

        return DB::transaction(function () use ($actorId, $role, $ip, $ua) {
            $this->roleRepo->update($role, ['status' => 'inactive']);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'role.admin.delete',
                'entity_name' => 'roles',
                'entity_id'   => $role->id,
                'old_values'  => ['status' => 'active'],
                'new_values'  => ['status' => 'inactive'],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $role->fresh();
        });
    }

    public function restore(int $actorId, int $id, string $ip, string $ua): Role
    {
        $role = $this->roleRepo->findOrFail($id);

        if ($role->status === 'active') {
            throw new RbacException('RESOURCE_NOT_DELETED', 'El rol no está inactivo.', 409);
        }

        return DB::transaction(function () use ($actorId, $role, $ip, $ua) {
            $this->roleRepo->update($role, ['status' => 'active']);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'role.admin.restore',
                'entity_name' => 'roles',
                'entity_id'   => $role->id,
                'old_values'  => ['status' => 'inactive'],
                'new_values'  => ['status' => 'active'],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $role->fresh();
        });
    }

    public function audit(int $id, array $filters)
    {
        $this->roleRepo->findOrFail($id);

        return $this->auditService->forEntity('roles', $id, $filters);
    }
}
