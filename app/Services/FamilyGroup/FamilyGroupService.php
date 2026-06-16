<?php

namespace App\Services\FamilyGroup;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\FamilyGroup;
use App\Repositories\FamilyGroup\FamilyGroupMemberRepository;
use App\Repositories\FamilyGroup\FamilyGroupPreferenceRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FamilyGroupService
{
    private $groupRepo;
    private $memberRepo;
    private $prefRepo;

    public function __construct(
        FamilyGroupRepository $groupRepo,
        FamilyGroupMemberRepository $memberRepo,
        FamilyGroupPreferenceRepository $prefRepo
    ) {
        $this->groupRepo  = $groupRepo;
        $this->memberRepo = $memberRepo;
        $this->prefRepo   = $prefRepo;
    }

    public function list(int $userId): Collection
    {
        return $this->groupRepo->listForUser($userId);
    }

    public function show(int $groupId, int $userId): FamilyGroup
    {
        return $this->groupRepo->findOrFailForUser($groupId, $userId);
    }

    public function create(int $userId, array $data, string $ip, string $ua): FamilyGroup
    {
        if ($this->groupRepo->userHasActiveGroup($userId)) {
            throw new FamilyGroupException(
                'USER_ALREADY_HAS_FAMILY_GROUP',
                'Ya pertenecés a un grupo familiar activo.',
                409
            );
        }

        return DB::transaction(function () use ($userId, $data, $ip, $ua) {
            $group = $this->groupRepo->create([
                'name'          => trim($data['name']),
                'owner_user_id' => $userId,
                'status'        => 'active',
            ]);

            $this->memberRepo->create([
                'family_group_id' => $group->id,
                'user_id'         => $userId,
                'role_in_group'   => 'owner',
                'status'          => 'active',
                'joined_at'       => now(),
            ]);

            $this->prefRepo->create([
                'family_group_id'           => $group->id,
                'allow_auto_stock_discount' => true,
            ]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'family_group.create',
                'entity_name' => 'family_groups',
                'entity_id'   => (string) $group->id,
                'old_values'  => null,
                'new_values'  => ['name' => $group->name, 'status' => $group->status],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $group;
        });
    }

    public function update(int $groupId, int $userId, array $data, string $ip, string $ua): FamilyGroup
    {
        $group = $this->groupRepo->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);

        return DB::transaction(function () use ($group, $userId, $data, $ip, $ua) {
            $allowed  = ['name', 'status'];
            $filtered = array_filter(
                array_intersect_key($data, array_flip($allowed)),
                function ($v) { return $v !== null; }
            );
            if (isset($filtered['name'])) {
                $filtered['name'] = trim($filtered['name']);
            }
            $old = $group->only(array_keys($filtered));

            $updated = $this->groupRepo->update($group, $filtered);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'family_group.update',
                'entity_name' => 'family_groups',
                'entity_id'   => (string) $group->id,
                'old_values'  => $old,
                'new_values'  => $filtered,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $updated;
        });
    }

    public function delete(int $groupId, int $userId, string $ip, string $ua): FamilyGroup
    {
        $group = $this->groupRepo->findOrFailForUser($groupId, $userId);

        $membership = $this->memberRepo->findMembership($groupId, $userId);
        if (!$membership || $membership->role_in_group !== 'owner') {
            throw new FamilyGroupException(
                'FAMILY_GROUP_ACCESS_DENIED',
                'Solo el propietario puede eliminar el grupo.',
                403
            );
        }

        return DB::transaction(function () use ($group, $userId, $ip, $ua) {
            $deleted = $this->groupRepo->delete($group);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'family_group.delete',
                'entity_name' => 'family_groups',
                'entity_id'   => (string) $group->id,
                'old_values'  => ['deleted_at' => null],
                'new_values'  => ['deleted_at' => (string) now()],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $deleted;
        });
    }

    public function requireAdminOrOwner(int $groupId, int $userId): void
    {
        $membership = $this->memberRepo->findMembership($groupId, $userId);
        if (!$membership || !in_array($membership->role_in_group, ['owner', 'admin'])) {
            throw new FamilyGroupException(
                'FAMILY_GROUP_ACCESS_DENIED',
                'Se requiere rol de propietario o administrador.',
                403
            );
        }
    }
}
