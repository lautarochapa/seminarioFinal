<?php

namespace App\Repositories\FamilyGroup;

use App\FamilyGroupPreference;

class FamilyGroupPreferenceRepository
{
    public function findByGroup(int $groupId): ?FamilyGroupPreference
    {
        return FamilyGroupPreference::where('family_group_id', $groupId)->first();
    }

    public function create(array $data): FamilyGroupPreference
    {
        return FamilyGroupPreference::create($data);
    }

    public function update(FamilyGroupPreference $pref, array $data): FamilyGroupPreference
    {
        $pref->update($data);
        return $pref->fresh();
    }

    public function upsert(int $groupId, array $data): FamilyGroupPreference
    {
        $pref = FamilyGroupPreference::firstOrCreate(
            ['family_group_id' => $groupId],
            ['allow_auto_stock_discount' => true]
        );
        $pref->update($data);
        return $pref->fresh();
    }
}
