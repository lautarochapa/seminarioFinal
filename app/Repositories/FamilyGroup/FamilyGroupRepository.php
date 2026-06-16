<?php

namespace App\Repositories\FamilyGroup;

use App\FamilyGroup;
use App\FamilyGroupMember;

class FamilyGroupRepository
{
    public function findForUser(int $groupId, int $userId): ?FamilyGroup
    {
        return FamilyGroup::where('id', $groupId)
            ->whereHas('members', function ($q) use ($userId) {
                $q->where('user_id', $userId)->where('status', 'active');
            })
            ->first();
    }

    public function findOrFailForUser(int $groupId, int $userId): FamilyGroup
    {
        $group = $this->findForUser($groupId, $userId);
        if (!$group) {
            throw new \App\Exceptions\FamilyGroup\FamilyGroupException(
                'FAMILY_GROUP_ACCESS_DENIED',
                'No tenés acceso a este grupo familiar.',
                403
            );
        }
        return $group;
    }

    public function listForUser(int $userId)
    {
        return FamilyGroup::whereHas('members', function ($q) use ($userId) {
                $q->where('user_id', $userId)->where('status', 'active');
            })
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get();
    }

    public function create(array $data): FamilyGroup
    {
        return FamilyGroup::create($data);
    }

    public function update(FamilyGroup $group, array $data): FamilyGroup
    {
        $group->update($data);
        return $group->fresh();
    }

    public function delete(FamilyGroup $group): FamilyGroup
    {
        $group->delete();
        return FamilyGroup::withTrashed()->find($group->id);
    }

    public function userHasActiveGroup(int $userId): bool
    {
        return FamilyGroupMember::join('family_groups', 'family_groups.id', '=', 'family_group_members.family_group_id')
            ->where('family_group_members.user_id', $userId)
            ->where('family_group_members.status', 'active')
            ->where('family_groups.status', 'active')
            ->whereNull('family_groups.deleted_at')
            ->exists();
    }
}
