<?php

namespace App\Repositories\MealPlanPreferences;

use App\MealPlanPreference;

class MealPlanPreferenceRepository
{
    /**
     * Preferencia a nivel grupo (user_id NULL). Es la que lee
     * MealPlanGenerationRepository::groupPreference() al generar el plan.
     */
    public function findGroupLevel(int $groupId): ?MealPlanPreference
    {
        return MealPlanPreference::where('family_group_id', $groupId)
            ->whereNull('user_id')
            ->first();
    }

    public function upsertGroupLevel(int $groupId, array $data): MealPlanPreference
    {
        $pref = MealPlanPreference::firstOrCreate(
            ['family_group_id' => $groupId, 'user_id' => null]
        );

        if (!empty($data)) {
            $pref->update($data);
        }

        return $pref->fresh();
    }
}
