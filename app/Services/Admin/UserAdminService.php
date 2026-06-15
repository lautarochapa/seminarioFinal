<?php

namespace App\Services\Admin;

use App\AuditLog;
use App\Exceptions\Rbac\RbacException;
use App\Repositories\Admin\UserRepository;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserAdminService
{
    private $userRepo;
    private $auditService;

    public function __construct(UserRepository $userRepo, AuditAdminService $auditService)
    {
        $this->userRepo     = $userRepo;
        $this->auditService = $auditService;
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->userRepo->paginate($filters);
    }

    public function show(int $id): User
    {
        return $this->userRepo->findOrFail($id);
    }

    public function create(int $actorId, array $data, string $ip, string $ua): User
    {
        $email = strtolower(trim($data['email']));

        if (User::where('email', $email)->exists()) {
            throw new RbacException('USER_EMAIL_ALREADY_EXISTS', 'El email ya está en uso.', 409);
        }

        return DB::transaction(function () use ($actorId, $data, $email, $ip, $ua) {
            $user = $this->userRepo->create([
                'name'     => $data['name'],
                'lastname' => $data['lastname'] ?? null,
                'email'    => $email,
                'password' => Hash::make($data['password']),
                'status'   => $data['status'] ?? 'active',
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'user.admin.create',
                'entity_name' => 'users',
                'entity_id'   => $user->id,
                'old_values'  => null,
                'new_values'  => ['email' => $user->email, 'name' => $user->name, 'status' => $user->status],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $user;
        });
    }

    public function update(int $actorId, int $id, array $data, string $ip, string $ua): User
    {
        $user = $this->userRepo->findOrFail($id);

        return DB::transaction(function () use ($actorId, $user, $data, $ip, $ua) {
            $allowed  = ['name', 'lastname', 'username', 'phone', 'avatar_url', 'status'];
            $filtered = array_intersect_key($data, array_flip($allowed));
            $old      = $user->only($allowed);

            $updated = $this->userRepo->update($user, $filtered);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'user.admin.update',
                'entity_name' => 'users',
                'entity_id'   => $user->id,
                'old_values'  => $old,
                'new_values'  => $filtered,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $updated;
        });
    }

    public function delete(int $actorId, int $id, string $ip, string $ua): User
    {
        $user = $this->userRepo->findOrFail($id);

        return DB::transaction(function () use ($actorId, $user, $ip, $ua) {
            $user->delete();

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'user.admin.delete',
                'entity_name' => 'users',
                'entity_id'   => $user->id,
                'old_values'  => ['deleted_at' => null],
                'new_values'  => ['deleted_at' => (string) now()],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return User::withTrashed()->find($user->id);
        });
    }

    public function restore(int $actorId, int $id, string $ip, string $ua): User
    {
        $user = $this->userRepo->findWithTrashedOrFail($id);

        if (!$user->trashed()) {
            throw new RbacException('RESOURCE_NOT_DELETED', 'El usuario no está eliminado.', 409);
        }

        return DB::transaction(function () use ($actorId, $user, $ip, $ua) {
            $user->restore();

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'user.admin.restore',
                'entity_name' => 'users',
                'entity_id'   => $user->id,
                'old_values'  => ['deleted_at' => $user->deleted_at],
                'new_values'  => ['deleted_at' => null],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $user->fresh();
        });
    }

    public function audit(int $id, array $filters)
    {
        $this->userRepo->findWithTrashedOrFail($id);

        return $this->auditService->forEntity('users', $id, $filters);
    }
}
