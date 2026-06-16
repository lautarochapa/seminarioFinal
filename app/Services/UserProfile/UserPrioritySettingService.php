<?php

namespace App\Services\UserProfile;

use App\AuditLog;
use App\Repositories\UserProfile\UserPrioritySettingRepository;
use App\UserPrioritySetting;

class UserPrioritySettingService
{
    private $repo;

    public function __construct(UserPrioritySettingRepository $repo)
    {
        $this->repo = $repo;
    }

    public function show(int $actorId): ?UserPrioritySetting
    {
        return $this->repo->findByUserId($actorId);
    }

    public function update(int $actorId, array $data, string $ip, string $ua): UserPrioritySetting
    {
        $existing = $this->repo->findByUserId($actorId);

        // Detect if any value actually changed
        $changed = false;
        if ($existing) {
            foreach ($data as $k => $v) {
                if ($existing->$k != $v) {
                    $changed = true;
                    break;
                }
            }
        } else {
            $changed = true;
        }

        $beforeValues = $existing
            ? $existing->only(['health_weight', 'budget_weight', 'time_weight', 'stock_usage_weight', 'preferred_mode'])
            : [];

        $setting = $this->repo->upsert($actorId, $data);

        if ($changed) {
            AuditLog::create([
                'user_id'     => $actorId,
                'action'      => 'update',
                'entity_name' => 'user_priority_settings',
                'entity_id'   => (string) $actorId,
                'old_values'  => $beforeValues,
                'new_values'  => $data,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);
        }

        return $setting;
    }
}
