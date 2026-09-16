<?php

namespace App\Services\Onboarding;

use App\Repositories\UserProfile\UserProfileRepository;
use Illuminate\Support\Facades\DB;

class OnboardingStatusService
{
    private $profileRepo;

    public function __construct(UserProfileRepository $profileRepo)
    {
        $this->profileRepo = $profileRepo;
    }

    public function forUser(int $userId): array
    {
        $profile         = $this->profileRepo->findProfileByUserId($userId);
        $objectivesCount = $this->profileRepo->getObjectivesForUser($userId)->count();

        $hasHeight    = $profile && $profile->height_cm !== null;
        $hasWeight    = $profile && $profile->current_weight_kg !== null;
        $hasMeals     = $profile && $profile->meals_per_day !== null && (int) $profile->meals_per_day >= 1;
        $hasObjective = $objectivesCount > 0;

        $groupsCount = DB::table('family_group_members')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->count();
        $hasGroup = $groupsCount > 0;

        $missingBasic = [];
        if (!$hasHeight) {
            $missingBasic[] = 'height_cm';
        }
        if (!$hasWeight) {
            $missingBasic[] = 'current_weight_kg';
        }

        $steps = [
            'basic_profile' => [
                'complete' => $hasHeight && $hasWeight,
                'missing'  => $missingBasic,
                'has_target_weight' => $profile && $profile->target_weight_kg !== null,
            ],
            'objective' => [
                'complete'         => $hasObjective,
                'objectives_count' => $objectivesCount,
            ],
            'meals_per_day' => [
                'complete' => $hasMeals,
                'value'    => $profile && $profile->meals_per_day !== null ? (int) $profile->meals_per_day : null,
            ],
            'food_preferences' => [
                'complete'                => true,
                'optional'                => true,
                'restrictions_count'      => DB::table('user_dietary_restrictions')->where('user_id', $userId)->count(),
                'allergies_count'         => DB::table('user_allergies')->where('user_id', $userId)->count(),
                'health_conditions_count' => DB::table('user_health_conditions')->where('user_id', $userId)->count(),
            ],
            'family_group' => [
                'complete'     => $hasGroup,
                'groups_count' => $groupsCount,
            ],
        ];

        $requiredOrder = ['basic_profile', 'objective', 'meals_per_day', 'family_group'];
        $nextStep = null;
        foreach ($requiredOrder as $key) {
            if (!$steps[$key]['complete']) {
                $nextStep = $key;
                break;
            }
        }

        return [
            'complete'         => $nextStep === null,
            'next_step'        => $nextStep,
            'required_steps'   => $requiredOrder,
            'completed_count'  => count(array_filter($requiredOrder, function ($k) use ($steps) {
                return $steps[$k]['complete'];
            })),
            'steps'            => $steps,
        ];
    }

    public function isComplete(int $userId): bool
    {
        return $this->forUser($userId)['complete'];
    }
}
