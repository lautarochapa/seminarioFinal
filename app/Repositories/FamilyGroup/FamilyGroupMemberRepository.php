<?php

namespace App\Repositories\FamilyGroup;

use App\FamilyGroupMember;
use Illuminate\Database\Eloquent\Collection;

class FamilyGroupMemberRepository
{
    public function listByGroup(int $groupId): Collection
    {
        return FamilyGroupMember::with('user')
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->get();
    }

    public function findMembership(int $groupId, int $userId): ?FamilyGroupMember
    {
        return FamilyGroupMember::where('family_group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }

    public function findInGroup(int $groupId, int $membershipId): ?FamilyGroupMember
    {
        return FamilyGroupMember::where('id', $membershipId)
            ->where('family_group_id', $groupId)
            ->first();
    }

    public function create(array $data): FamilyGroupMember
    {
        return FamilyGroupMember::create($data);
    }

    public function update(FamilyGroupMember $membership, array $data): FamilyGroupMember
    {
        $membership->update($data);
        return $membership->fresh();
    }

    public function delete(FamilyGroupMember $membership): void
    {
        $membership->delete();
    }

    public function countOwners(int $groupId): int
    {
        return FamilyGroupMember::where('family_group_id', $groupId)
            ->where('role_in_group', 'owner')
            ->where('status', 'active')
            ->count();
    }
}
