<?php

namespace App\Repositories\UserProfile;

use App\UserPrioritySetting;

class UserPrioritySettingRepository
{
    public function findByUserId(int $userId): ?UserPrioritySetting
    {
        return UserPrioritySetting::where('user_id', $userId)->first();
    }

    public function upsert(int $userId, array $data): UserPrioritySetting
    {
        $setting = UserPrioritySetting::firstOrNew(['user_id' => $userId]);
        $setting->fill($data)->save();
        return $setting->fresh();
    }
}
