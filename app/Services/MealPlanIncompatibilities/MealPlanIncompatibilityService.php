<?php

namespace App\Services\MealPlanIncompatibilities;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Exceptions\MealPlanIncompatibilities\MealPlanIncompatibilityException;
use App\MealPlanItem;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\MealPlanIncompatibilities\MealPlanIncompatibilityRepository;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MealPlanIncompatibilityService
{
    // Embedded thresholds: no DB thresholds table exists in the current schema.
    // Based on common dietary guidelines (WHO): ~2000mg sodium/day, ~50g sugar/day.
    const SODIUM_THRESHOLD_MG = 600.0;
    const SUGAR_THRESHOLD_G   = 12.0;

    private MealPlanIncompatibilityRepository $repo;
    private FamilyGroupRepository             $groupRepo;

    public function __construct(
        MealPlanIncompatibilityRepository $repo,
        FamilyGroupRepository             $groupRepo
    ) {
        $this->repo      = $repo;
        $this->groupRepo = $groupRepo;
    }

    public function list(User $user, int $groupId, int $planId): Collection
    {
        $this->assertMember($user, $groupId);
        $this->assertPlanForGroup($planId, $groupId);
        return $this->repo->listForPlan($planId);
    }

    public function check(User $user, int $groupId, int $planId, string $ip, string $ua): Collection
    {
        $this->assertMember($user, $groupId);
        $this->assertPlanForGroup($planId, $groupId);

        $items = $this->repo->planItemsWithNutrition($planId);

        DB::transaction(function () use ($planId, $items) {
            $this->repo->clearForPlan($planId);
            $seenKeys = [];
            foreach ($items as $item) {
                foreach ($this->detectForItem($item, $seenKeys) as $data) {
                    $this->repo->createIncompatibility($data);
                }
            }
        });

        $result = $this->repo->listForPlan($planId);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_incompatibilities_checked',
            'entity_name' => 'meal_plans',
            'entity_id'   => $planId,
            'old_values'  => null,
            'new_values'  => ['incompatibilities_found' => $result->count()],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $result;
    }

    private function detectForItem(MealPlanItem $item, array &$seenKeys): array
    {
        $records   = [];
        $nutrition = $item->recipe ? $item->recipe->nutrition : null;

        if (!$nutrition) {
            return $records;
        }

        $sodiumKey = "high_sodium_{$item->id}";
        if (
            $nutrition->sodium_per_serving !== null
            && $nutrition->sodium_per_serving > self::SODIUM_THRESHOLD_MG
            && !isset($seenKeys[$sodiumKey])
        ) {
            $seenKeys[$sodiumKey] = true;
            $records[] = [
                'meal_plan_id'         => $item->meal_plan_id,
                'meal_plan_item_id'    => $item->id,
                'user_id'              => null,
                'incompatibility_type' => 'high_sodium',
                'message'              => 'La receta supera ' . self::SODIUM_THRESHOLD_MG . 'mg de sodio por porcion.',
                'severity'             => 'warning',
                'status'               => 'open',
            ];
        }

        $sugarKey = "high_sugar_{$item->id}";
        if (
            $nutrition->sugar_per_serving !== null
            && $nutrition->sugar_per_serving > self::SUGAR_THRESHOLD_G
            && !isset($seenKeys[$sugarKey])
        ) {
            $seenKeys[$sugarKey] = true;
            $records[] = [
                'meal_plan_id'         => $item->meal_plan_id,
                'meal_plan_item_id'    => $item->id,
                'user_id'              => null,
                'incompatibility_type' => 'high_sugar',
                'message'              => 'La receta supera ' . self::SUGAR_THRESHOLD_G . 'g de azucar por porcion.',
                'severity'             => 'warning',
                'status'               => 'open',
            ];
        }

        return $records;
    }

    private function assertMember(User $user, int $groupId): void
    {
        try {
            $this->groupRepo->findOrFailForUser($groupId, $user->id);
        } catch (FamilyGroupException $e) {
            throw MealPlanIncompatibilityException::groupNotFound();
        }
    }

    private function assertPlanForGroup(int $planId, int $groupId): void
    {
        if (!$this->repo->planExistsForGroup($planId, $groupId)) {
            throw MealPlanIncompatibilityException::planNotFound();
        }
    }
}
