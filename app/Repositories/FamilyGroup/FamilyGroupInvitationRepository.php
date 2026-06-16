<?php

namespace App\Repositories\FamilyGroup;

use App\FamilyGroupInvitation;

class FamilyGroupInvitationRepository
{
    public function findPendingForGroup(int $groupId, string $email): ?FamilyGroupInvitation
    {
        return FamilyGroupInvitation::where('family_group_id', $groupId)
            ->where('invited_email', strtolower($email))
            ->where('status', 'pending')
            ->first();
    }

    public function create(array $data): FamilyGroupInvitation
    {
        return FamilyGroupInvitation::create($data);
    }

    public function findById(int $id): ?FamilyGroupInvitation
    {
        return FamilyGroupInvitation::find($id);
    }

    public function findByIdForUpdate(int $id): ?FamilyGroupInvitation
    {
        return FamilyGroupInvitation::lockForUpdate()->find($id);
    }

    public function accept(FamilyGroupInvitation $invitation): FamilyGroupInvitation
    {
        $invitation->update([
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);
        return $invitation->fresh();
    }
}
