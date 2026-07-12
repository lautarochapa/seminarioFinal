<?php

namespace App\Services\Admin;

use App\AuditLog;
use App\Exceptions\Rbac\RbacException;
use App\Permission;
use App\Repositories\Admin\RoleRepository;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    private $roleRepo;

    public function __construct(RoleRepository $roleRepo)
    {
        $this->roleRepo = $roleRepo;
    }

    public function assign(int $actorId, int $roleId, int $permissionId, string $ip, string $ua): void
    {
        $role       = $this->roleRepo->findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);

        $exists = DB::table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();

        if ($exists) {
            throw new RbacException('ROLE_PERMISSION_ALREADY_EXISTS', 'El permiso ya está asignado al rol.', 409);
        }

        DB::transaction(function () use ($actorId, $role, $permission, $roleId, $permissionId, $ip, $ua) {
            DB::table('role_permissions')->insert([
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
                'created_at'    => now(),
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'role.permission.assign',
                'entity_name' => 'roles',
                'entity_id'   => $roleId,
                'old_values'  => null,
                'new_values'  => ['permission_id' => $permissionId, 'permission_code' => $permission->code],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);
        });
    }

    public function remove(int $actorId, int $roleId, int $permissionId, string $ip, string $ua): void
    {
        $this->roleRepo->findOrFail($roleId);

        $deleted = DB::transaction(function () use ($actorId, $roleId, $permissionId, $ip, $ua) {
            $count = DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();

            if ($count === 0) {
                return 0;
            }

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'role.permission.remove',
                'entity_name' => 'roles',
                'entity_id'   => $roleId,
                'old_values'  => ['permission_id' => $permissionId],
                'new_values'  => null,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $count;
        });

        if ($deleted === 0) {
            throw new RbacException('ROLE_PERMISSION_NOT_FOUND', 'El rol no tiene ese permiso.', 404);
        }
    }
}
