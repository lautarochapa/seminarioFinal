<?php

namespace App\Services\Admin;

use App\AuditLog;
use App\Exceptions\Rbac\RbacException;
use App\Repositories\Admin\UserRepository;
use App\Role;
use Illuminate\Support\Facades\DB;

class UserRoleService
{
    private $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function assign(int $actorId, int $userId, int $roleId, string $ip, string $ua): void
    {
        $user = $this->userRepo->findOrFail($userId);
        $role = Role::findOrFail($roleId);

        $exists = DB::table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->exists();

        if ($exists) {
            throw new RbacException('USER_ROLE_ALREADY_EXISTS', 'El rol ya está asignado al usuario.', 409);
        }

        DB::transaction(function () use ($actorId, $user, $role, $userId, $roleId, $ip, $ua) {
            DB::table('user_roles')->insert([
                'user_id'    => $userId,
                'role_id'    => $roleId,
                'created_at' => now(),
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'user.role.assign',
                'entity_name' => 'users',
                'entity_id'   => $userId,
                'old_values'  => null,
                'new_values'  => ['role_id' => $roleId, 'role_code' => $role->code],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);
        });
    }

    public function remove(int $actorId, int $userId, int $roleId, string $ip, string $ua): void
    {
        $this->userRepo->findOrFail($userId);

        $deleted = DB::transaction(function () use ($actorId, $userId, $roleId, $ip, $ua) {
            $count = DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->delete();

            if ($count === 0) {
                return 0;
            }

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'user.role.remove',
                'entity_name' => 'users',
                'entity_id'   => $userId,
                'old_values'  => ['role_id' => $roleId],
                'new_values'  => null,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $count;
        });

        if ($deleted === 0) {
            throw new RbacException('USER_ROLE_NOT_FOUND', 'El usuario no tiene ese rol.', 404);
        }
    }
}
