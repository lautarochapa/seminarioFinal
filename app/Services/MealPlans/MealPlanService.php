<?php

namespace App\Services\MealPlans;

use App\AuditLog;
use App\Exceptions\MealPlans\MealPlanException;
use App\MealPlan;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\MealPlans\MealPlanRepository;
use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MealPlanService
{
    private MealPlanRepository    $repo;
    private FamilyGroupRepository $groupRepo;

    public function __construct(MealPlanRepository $repo, FamilyGroupRepository $groupRepo)
    {
        $this->repo      = $repo;
        $this->groupRepo = $groupRepo;
    }

    public function list(User $user, int $groupId, array $filters): LengthAwarePaginator
    {
        $this->assertMember($user, $groupId);
        return $this->repo->paginate($groupId, $filters);
    }

    public function show(User $user, int $groupId, int $planId): MealPlan
    {
        $this->assertMember($user, $groupId);
        $plan = $this->repo->findForGroup($planId, $groupId);
        if (!$plan) {
            throw MealPlanException::notFound();
        }
        return $plan;
    }

    public function create(User $user, int $groupId, array $input, string $ip, string $ua): MealPlan
    {
        $this->assertMember($user, $groupId);

        $items = $this->resolveItems($input['items'] ?? []);

        $plan = DB::transaction(function () use ($user, $groupId, $input, $items) {
            return $this->repo->create([
                'family_group_id' => $groupId,
                'created_by'      => $user->id,
                'period_type'     => $input['period_type'],
                'start_date'      => $input['start_date'],
                'end_date'        => $input['end_date'],
                'mode'            => $input['mode'] ?? null,
                'status'          => 'draft',
            ], $items);
        });

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_created',
            'entity_name' => 'meal_plans',
            'entity_id'   => $plan->id,
            'old_values'  => null,
            'new_values'  => ['family_group_id' => $groupId, 'period_type' => $plan->period_type, 'start_date' => $plan->start_date, 'end_date' => $plan->end_date],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $plan;
    }

    public function update(User $user, int $groupId, int $planId, array $input, string $ip, string $ua): MealPlan
    {
        $this->assertMember($user, $groupId);

        $plan = $this->repo->findForGroup($planId, $groupId);
        if (!$plan) {
            throw MealPlanException::notFound();
        }

        $items = isset($input['items']) ? $this->resolveItems($input['items']) : null;

        $allowed = ['period_type', 'start_date', 'end_date', 'mode'];
        $fields  = array_intersect_key($input, array_flip($allowed));
        $old     = $plan->only(array_keys($fields));

        $plan = DB::transaction(function () use ($plan, $fields, $items) {
            return $this->repo->update($plan, $fields, $items);
        });

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_updated',
            'entity_name' => 'meal_plans',
            'entity_id'   => $plan->id,
            'old_values'  => $old,
            'new_values'  => $fields,
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $plan;
    }

    public function destroy(User $user, int $groupId, int $planId, string $ip, string $ua): void
    {
        $this->assertMember($user, $groupId);

        $plan = $this->repo->findForGroup($planId, $groupId);
        if (!$plan) {
            throw MealPlanException::notFound();
        }

        $this->repo->delete($plan);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_deleted',
            'entity_name' => 'meal_plans',
            'entity_id'   => $plan->id,
            'old_values'  => ['status' => $plan->status],
            'new_values'  => ['deleted' => true],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);
    }

    private function assertMember(User $user, int $groupId): void
    {
        try {
            $this->groupRepo->findOrFailForUser($groupId, $user->id);
        } catch (\App\Exceptions\FamilyGroup\FamilyGroupException $e) {
            throw MealPlanException::groupNotFound();
        }
    }

    private function resolveItems(array $rawItems): array
    {
        $resolved = [];
        foreach ($rawItems as $i => $raw) {
            $hasRecipe    = !empty($raw['recipe_id']);
            $hasFree      = !empty($raw['free_meal_description']);
            $isEatingOut  = !empty($raw['is_eating_out']);

            if (!$hasRecipe && !$hasFree && !$isEatingOut) {
                throw MealPlanException::itemMissingContent($i);
            }

            $mealTypeId = (int) $raw['meal_type_id'];
            if (!$this->repo->findActiveMealType($mealTypeId)) {
                throw MealPlanException::mealTypeNotFound($mealTypeId);
            }

            $recipeId = null;
            if ($hasRecipe) {
                $recipeId = (int) $raw['recipe_id'];
                if (!$this->repo->findActiveRecipe($recipeId)) {
                    throw MealPlanException::recipeNotFound($recipeId);
                }
            }

            $resolved[] = [
                'date'                  => $raw['date'],
                'meal_type_id'          => $mealTypeId,
                'recipe_id'             => $recipeId,
                'free_meal_description' => $raw['free_meal_description'] ?? null,
                'is_eating_out'         => (bool) ($raw['is_eating_out'] ?? false),
                'servings_total'        => isset($raw['servings_total']) ? (float) $raw['servings_total'] : null,
                'notes'                 => $raw['notes'] ?? null,
                'status'                => 'planned',
            ];
        }
        return $resolved;
    }
}
