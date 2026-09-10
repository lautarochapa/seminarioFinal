<?php

namespace App\Services\MealPlanPreferences;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\MealPlanPreference;
use App\Repositories\FamilyGroup\FamilyGroupMemberRepository;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\MealPlanPreferences\MealPlanPreferenceRepository;
use Illuminate\Support\Facades\DB;

class MealPlanPreferenceService
{
    private $groupRepo;
    private $memberRepo;
    private $prefRepo;

    public function __construct(
        FamilyGroupRepository $groupRepo,
        FamilyGroupMemberRepository $memberRepo,
        MealPlanPreferenceRepository $prefRepo
    ) {
        $this->groupRepo  = $groupRepo;
        $this->memberRepo = $memberRepo;
        $this->prefRepo   = $prefRepo;
    }

    /**
     * Devuelve la preferencia de grupo. Si todavia no existe, devuelve un modelo
     * NO persistido con los valores por defecto del schema (no crea la fila:
     * eso solo ocurre al hacer PATCH).
     */
    public function show(int $groupId, int $userId): MealPlanPreference
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        return $this->prefRepo->findGroupLevel($groupId) ?: new MealPlanPreference([
            'family_group_id'   => $groupId,
            'user_id'           => null,
            'avoid_repetition'  => true,
            'respect_budget'    => true,
            'respect_nutrition' => true,
            'respect_stock'     => true,
            'preferred_mode'    => null,
        ]);
    }

    public function update(int $groupId, int $userId, array $data, string $ip, string $ua): MealPlanPreference
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);
        $this->requireAdminOrOwner($groupId, $userId);

        return DB::transaction(function () use ($groupId, $userId, $data, $ip, $ua) {
            $allowed  = ['avoid_repetition', 'respect_budget', 'respect_nutrition', 'respect_stock', 'preferred_mode'];
            $filtered = array_intersect_key($data, array_flip($allowed));

            $before = optional($this->prefRepo->findGroupLevel($groupId))->only($allowed);
            $pref   = $this->prefRepo->upsertGroupLevel($groupId, $filtered);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'meal_plan.preferences.update',
                'entity_name' => 'meal_plan_preferences',
                'entity_id'   => (string) $pref->id,
                'old_values'  => $before,
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
        if (!$membership || !in_array($membership->role_in_group, ['owner', 'admin'], true)) {
            throw new FamilyGroupException('FAMILY_GROUP_ACCESS_DENIED', 'Se requiere rol de propietario o administrador.', 403);
        }
    }
}
