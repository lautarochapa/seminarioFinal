<?php

namespace App\Repositories\Professional;

use App\ProfessionalUserLink;
use Illuminate\Support\Collection;

class ProfessionalLinkRepository
{
    public function listForUser(int $userId): Collection
    {
        return ProfessionalUserLink::where('user_id', $userId)
            ->where('status', 'active')
            ->get();
    }

    public function findByIdForUser(int $id, int $userId): ?ProfessionalUserLink
    {
        return ProfessionalUserLink::where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function findActiveLinkForPair(int $userId, int $professionalId): ?ProfessionalUserLink
    {
        return ProfessionalUserLink::where('user_id', $userId)
            ->where('professional_user_id', $professionalId)
            ->where('status', 'active')
            ->first();
    }

    public function findActiveLinkByProfessional(int $userId, int $professionalId): ?ProfessionalUserLink
    {
        return ProfessionalUserLink::where('user_id', $userId)
            ->where('professional_user_id', $professionalId)
            ->where('status', 'active')
            ->first();
    }

    public function listLinkedUsersForProfessional(int $professionalId): Collection
    {
        return ProfessionalUserLink::where('professional_user_id', $professionalId)
            ->where('status', 'active')
            ->with('user')
            ->get();
    }

    public function create(array $data): ProfessionalUserLink
    {
        return ProfessionalUserLink::create($data);
    }

    public function update(ProfessionalUserLink $link, array $data): ProfessionalUserLink
    {
        $link->fill($data)->save();
        return $link->fresh();
    }

    public function revoke(ProfessionalUserLink $link): ProfessionalUserLink
    {
        $link->status     = 'revoked';
        $link->revoked_at = now();
        $link->save();
        return $link;
    }
}
