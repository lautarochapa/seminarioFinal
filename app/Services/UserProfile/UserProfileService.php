<?php

namespace App\Services\UserProfile;

use App\AuditLog;
use App\Repositories\UserProfile\UserProfileRepository;
use Illuminate\Support\Facades\DB;

class UserProfileService
{
    private $profileRepo;

    public function __construct(UserProfileRepository $profileRepo)
    {
        $this->profileRepo = $profileRepo;
    }

    public function show(int $actorId): object
    {
        return $this->profileRepo->getProfileData($actorId);
    }

    public function update(int $actorId, array $data, string $ip, string $ua): object
    {
        return DB::transaction(function () use ($actorId, $data, $ip, $ua) {
            $user    = $this->profileRepo->findUserById($actorId);
            $profile = $this->profileRepo->findProfileByUserId($actorId);

            $userFields    = ['name', 'lastname', 'phone'];
            $profileFields = ['birth_date', 'gender', 'height_cm', 'current_weight_kg', 'target_weight_kg', 'activity_level', 'meals_per_day', 'notes'];
            $prefFields    = ['uses_app_for_health', 'uses_app_for_budget', 'uses_app_for_organization'];

            // User updates with string normalization
            $userUpdates = array_intersect_key($data, array_flip($userFields));
            foreach (['name', 'lastname', 'phone'] as $field) {
                if (isset($userUpdates[$field]) && is_string($userUpdates[$field])) {
                    $userUpdates[$field] = trim($userUpdates[$field]);
                }
            }

            // Profile and preference updates
            $profileUpdates = array_intersect_key($data, array_flip($profileFields));
            $prefUpdates    = isset($data['preferences']) ? array_intersect_key($data['preferences'], array_flip($prefFields)) : [];
            $profileUpdates = array_merge($profileUpdates, $prefUpdates);

            // Detect user changes
            $beforeUser      = [];
            $afterUser       = [];
            $changedUserData = [];
            foreach ($userUpdates as $k => $v) {
                if ($user->$k !== $v) {
                    $beforeUser[$k]      = $user->$k;
                    $changedUserData[$k] = $v;
                    $afterUser[$k]       = $v;
                }
            }

            if ($changedUserData) {
                $this->profileRepo->updateUser($user, $changedUserData);
            }

            // Detect profile changes
            $beforeProfile      = [];
            $afterProfile       = [];
            $changedProfileData = [];

            if ($profile) {
                foreach ($profileUpdates as $k => $v) {
                    if ($profile->$k != $v) {
                        $beforeProfile[$k]      = $profile->$k;
                        $changedProfileData[$k] = $v;
                        $afterProfile[$k]       = $v;
                    }
                }
                if ($changedProfileData) {
                    $this->profileRepo->updateProfile($profile, $changedProfileData);
                }
            } elseif ($profileUpdates) {
                $this->profileRepo->createProfile($actorId, $profileUpdates);
                $changedProfileData = $profileUpdates;
                $afterProfile       = $profileUpdates;
            }

            // Objectives sync
            $objectivesChanged = false;
            if (array_key_exists('objective_ids', $data)) {
                $this->profileRepo->syncObjectives($actorId, $data['objective_ids']);
                $objectivesChanged = true;
            }

            // Audit only when values actually changed
            if ($changedUserData || $changedProfileData || $objectivesChanged) {
                AuditLog::create([
                    'user_id'     => $actorId,
                    'action'      => 'update',
                    'entity_name' => 'user_profile',
                    'entity_id'   => (string) $actorId,
                    'old_values'  => array_merge($beforeUser, $beforeProfile),
                    'new_values'  => array_merge($afterUser, $afterProfile),
                    'ip_address'  => $ip,
                    'user_agent'  => $ua,
                ]);
            }

            return $this->profileRepo->getProfileData($actorId);
        });
    }
}
