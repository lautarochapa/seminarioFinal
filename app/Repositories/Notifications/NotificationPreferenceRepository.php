<?php

namespace App\Repositories\Notifications;

use App\NotificationPreference;
use Illuminate\Support\Collection;

class NotificationPreferenceRepository
{
    public function getForUser(int $userId): Collection
    {
        return NotificationPreference::where('user_id', $userId)
            ->where('status', 'active')
            ->get();
    }

    public function upsert(int $userId, string $notificationType, array $data): NotificationPreference
    {
        $pref = NotificationPreference::where('user_id', $userId)
            ->where('notification_type', $notificationType)
            ->first();

        if ($pref) {
            $pref->update($data);
            return $pref->fresh();
        }

        return NotificationPreference::create(array_merge($data, [
            'user_id'           => $userId,
            'notification_type' => $notificationType,
            'status'            => 'active',
        ]));
    }
}
