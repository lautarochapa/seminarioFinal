<?php

namespace App\Services\FamilyGroup;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\FamilyGroupMember;
use App\Repositories\FamilyGroup\FamilyGroupMemberRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FamilyGroupMemberService
{
    private $groupRepo;
    private $memberRepo;

    public function __construct(
        FamilyGroupRepository $groupRepo,
        FamilyGroupMemberRepository $memberRepo
    ) {
        $this->groupRepo  = $groupRepo;
        $this->memberRepo = $memberRepo;
    }

    public function list(int $groupId, int $userId): Collection
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        return $this->memberRepo->listByGroup($groupId);
    }

    public function add(int $groupId, int $actorId, array $data, string $ip, string $ua): FamilyGroupMember
    {
        $this->groupRepo->findOrFailForUser($groupId, $actorId);
        $this->requireAdminOrOwner($groupId, $actorId);

        $targetUser = User::find((int) $data['user_id']);
        if (!$targetUser) {
            throw new FamilyGroupException('FAMILY_MEMBER_NOT_FOUND', 'Usuario no encontrado.', 404);
        }

        if ($this->memberRepo->findMembership($groupId, $targetUser->id)) {
            throw new FamilyGroupException('USER_ALREADY_FAMILY_MEMBER', 'El usuario ya es miembro de este grupo.', 409);
        }

        if ($this->groupRepo->userHasActiveGroup($targetUser->id)) {
            throw new FamilyGroupException('USER_BELONGS_TO_ANOTHER_FAMILY_GROUP', 'El usuario ya pertenece a otro grupo familiar.', 409);
        }

        $role = $data['role'] ?? 'member';

        return DB::transaction(function () use ($groupId, $actorId, $targetUser, $role, $ip, $ua) {
            $membership = $this->memberRepo->create([
                'family_group_id' => $groupId,
                'user_id'         => $targetUser->id,
                'role_in_group'   => $role,
                'status'          => 'active',
                'joined_at'       => now(),
            ]);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'family_group.member.add',
                'entity_name' => 'family_group_members',
                'entity_id'   => (string) $membership->id,
                'old_values'  => null,
                'new_values'  => ['user_id' => $targetUser->id, 'role_in_group' => $role],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $membership->load('user');
        });
    }

    public function update(int $groupId, int $membershipId, int $actorId, array $data, string $ip, string $ua): FamilyGroupMember
    {
        $this->groupRepo->findOrFailForUser($groupId, $actorId);
        $this->requireAdminOrOwner($groupId, $actorId);

        $membership = $this->memberRepo->findInGroup($groupId, $membershipId);
        if (!$membership) {
            throw new FamilyGroupException('FAMILY_MEMBER_NOT_FOUND', 'Miembro no encontrado en este grupo.', 404);
        }

        $actorMembership = $this->memberRepo->findMembership($groupId, $actorId);

        $newRole = $data['role'] ?? null;
        if ($newRole === 'owner') {
            if ($actorMembership->role_in_group !== 'owner') {
                throw new FamilyGroupException('OWNER_MODIFICATION_FORBIDDEN', 'Solo el propietario puede asignar el rol de propietario.', 403);
            }
        }

        // Admin cannot modify the owner's role
        if ($actorMembership->role_in_group === 'admin' && $membership->role_in_group === 'owner') {
            throw new FamilyGroupException('OWNER_MODIFICATION_FORBIDDEN', 'No podés modificar al propietario del grupo.', 403);
        }

        return DB::transaction(function () use ($membership, $actorId, $data, $ip, $ua) {
            $allowed  = ['role_in_group', 'status'];
            $filtered = [];
            if (isset($data['role'])) {
                $filtered['role_in_group'] = $data['role'];
            }
            if (isset($data['status'])) {
                $filtered['status'] = $data['status'];
            }

            $old     = $membership->only(array_keys($filtered));
            $updated = $this->memberRepo->update($membership, $filtered);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'family_group.member.update',
                'entity_name' => 'family_group_members',
                'entity_id'   => (string) $membership->id,
                'old_values'  => $old,
                'new_values'  => $filtered,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $updated->load('user');
        });
    }

    public function remove(int $groupId, int $membershipId, int $actorId, string $ip, string $ua): void
    {
        $this->groupRepo->findOrFailForUser($groupId, $actorId);

        $membership = $this->memberRepo->findInGroup($groupId, $membershipId);
        if (!$membership) {
            throw new FamilyGroupException('FAMILY_MEMBER_NOT_FOUND', 'Miembro no encontrado en este grupo.', 404);
        }

        $actorMembership = $this->memberRepo->findMembership($groupId, $actorId);
        $isSelf = $membership->user_id === $actorId;

        // Only member removing themselves — allowed (unless last owner)
        if (!$isSelf) {
            // Must be admin or owner to remove others
            if (!$actorMembership || !in_array($actorMembership->role_in_group, ['owner', 'admin'])) {
                throw new FamilyGroupException('FAMILY_GROUP_ACCESS_DENIED', 'Se requiere rol de propietario o administrador.', 403);
            }
            // Admin cannot remove owner
            if ($actorMembership->role_in_group === 'admin' && $membership->role_in_group === 'owner') {
                throw new FamilyGroupException('OWNER_MODIFICATION_FORBIDDEN', 'No podés quitar al propietario del grupo.', 403);
            }
        }

        // Prevent last owner from leaving
        if ($membership->role_in_group === 'owner') {
            if ($this->memberRepo->countOwners($groupId) <= 1) {
                throw new FamilyGroupException('LAST_OWNER_REMOVAL_FORBIDDEN', 'No podés retirarte: sos el único propietario del grupo.', 409);
            }
        }

        DB::transaction(function () use ($membership, $actorId, $ip, $ua) {
            $this->memberRepo->delete($membership);

            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'family_group.member.remove',
                'entity_name' => 'family_group_members',
                'entity_id'   => (string) $membership->id,
                'old_values'  => ['user_id' => $membership->user_id, 'role_in_group' => $membership->role_in_group],
                'new_values'  => null,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);
        });
    }

    private function requireAdminOrOwner(int $groupId, int $userId): void
    {
        $membership = $this->memberRepo->findMembership($groupId, $userId);
        if (!$membership || !in_array($membership->role_in_group, ['owner', 'admin'])) {
            throw new FamilyGroupException('FAMILY_GROUP_ACCESS_DENIED', 'Se requiere rol de propietario o administrador.', 403);
        }
    }
}
