<?php

namespace App\Repositories\Consents;

use App\UserConsent;

class UserConsentRepository
{
    public function latestByUser(int $userId)
    {
        return UserConsent::where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->get()
            ->unique('consent_type')
            ->keyBy('consent_type');
    }

    public function create(array $data): UserConsent
    {
        return UserConsent::create($data);
    }
}
