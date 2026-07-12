<?php

namespace App\Repositories\UserProfile;

use App\User;
use App\UserProfile;
use Illuminate\Support\Facades\DB;

class UserProfileRepository
{
    public function findUserById(int $userId): User
    {
        return User::findOrFail($userId);
    }

    public function updateUser(User $user, array $data): User
    {
        $user->fill($data)->save();
        return $user->fresh();
    }

    public function findProfileByUserId(int $userId): ?UserProfile
    {
        return UserProfile::where('user_id', $userId)->first();
    }

    public function createProfile(int $userId, array $data): UserProfile
    {
        return UserProfile::create(array_merge(['user_id' => $userId], $data));
    }

    public function updateProfile(UserProfile $profile, array $data): UserProfile
    {
        $profile->fill($data)->save();
        return $profile->fresh();
    }

    public function getObjectivesForUser(int $userId)
    {
        return DB::table('user_objectives')
            ->join('objectives', 'objectives.id', '=', 'user_objectives.objective_id')
            ->where('user_objectives.user_id', $userId)
            ->where('user_objectives.is_active', true)
            ->where('objectives.status', 'active')
            ->whereNull('objectives.deleted_at')
            ->select('objectives.id', 'objectives.code', 'objectives.name')
            ->get();
    }

    public function syncObjectives(int $userId, array $objectiveIds): void
    {
        DB::table('user_objectives')->where('user_id', $userId)->delete();

        $now = now();
        foreach ($objectiveIds as $objectiveId) {
            DB::table('user_objectives')->insert([
                'user_id'      => $userId,
                'objective_id' => $objectiveId,
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function getProfileData(int $userId): object
    {
        return (object) [
            'user'       => $this->findUserById($userId),
            'profile'    => $this->findProfileByUserId($userId),
            'objectives' => $this->getObjectivesForUser($userId),
        ];
    }
}
