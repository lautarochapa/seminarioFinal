<?php

namespace App\Services\FamilyGroup;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\FamilyGroupPreference;
use App\Repositories\FamilyGroup\FamilyGroupMemberRepository;
use App\Repositories\FamilyGroup\FamilyGroupPreferenceRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use Illuminate\Support\Facades\DB;

class FamilyGroupPreferenceService
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

    public function show(int $groupId, int $userId): FamilyGroupPreference
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $pref = $this->prefRepo->findByGroup($groupId);
        if (!$pref) {
            throw new FamilyGroupException('FAMILY_PREFERENCES_NOT_FOUND', 'Preferencias no encontradas.', 404);
        }
        return $pref;
    }

    public function update(int $groupId, int $userId, array $data, string $ip, string $ua): FamilyGroupPreference
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);

        return DB::transaction(function () use ($groupId, $userId, $data, $ip, $ua) {
            $allowed  = ['default_budget_mode', 'default_shopping_mode', 'default_recipe_priority_mode', 'allow_auto_stock_discount'];
            $filtered = array_intersect_key($data, array_flip($allowed));

            $pref = $this->prefRepo->upsert($groupId, $filtered);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'family_group.preferences.update',
                'entity_name' => 'family_group_preferences',
                'entity_id'   => (string) $pref->id,
                'old_values'  => null,
                'new_values'  => $filtered,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $pref;
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
