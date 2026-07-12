<?php

namespace App\Services\MealPlanGeneration;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Exceptions\MealPlanGeneration\MealPlanGenerationException;
use App\MealPlan;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\MealPlanGeneration\MealPlanGenerationRepository;
use App\Repositories\MealPlans\MealPlanRepository;
use App\Repositories\RecipeSuggestions\RecipeSuggestionsRepository;
use App\User;
use Illuminate\Support\Facades\DB;

class MealPlanGenerationService
{
    private MealPlanGenerationRepository $genRepo;
    private MealPlanRepository           $planRepo;
    private RecipeSuggestionsRepository  $suggRepo;
    private FamilyGroupRepository        $groupRepo;

    public function __construct(
        MealPlanGenerationRepository $genRepo,
        MealPlanRepository           $planRepo,
        RecipeSuggestionsRepository  $suggRepo,
        FamilyGroupRepository        $groupRepo
    ) {
        $this->genRepo   = $genRepo;
        $this->planRepo  = $planRepo;
        $this->suggRepo  = $suggRepo;
        $this->groupRepo = $groupRepo;
    }

    public function generate(User $user, int $groupId, array $input, string $ip, string $ua): MealPlan
    {
        $this->assertMember($user, $groupId);

        $mealTypes  = $this->genRepo->activeMealTypes();
        $pref       = $this->genRepo->groupPreference($groupId);
        $avoidRep   = $pref ? (bool) $pref->avoid_repetition : true;
        $respBudget = $pref ? (bool) $pref->respect_budget   : false;

        $allowedIds = null;
        if ($respBudget) {
            $budget = $this->suggRepo->currentBudget($groupId);
            if ($budget && $budget->total_amount > 0) {
                $ids = $this->suggRepo->recipeIdsByMaxCost($groupId, (float) $budget->total_amount);
                if (!empty($ids)) {
                    $allowedIds = $ids;
                }
            }
        }

        $candidates = $this->genRepo->candidateRecipesForGroup($groupId, $allowedIds);

        if (empty($candidates)) {
            throw MealPlanGenerationException::noRecipesAvailable();
        }

        $items = $this->buildItems(
            $candidates,
            $mealTypes->all(),
            $input['start_date'],
            $input['end_date'],
            $avoidRep
        );

        $plan = DB::transaction(function () use ($user, $groupId, $input, $items) {
            return $this->planRepo->create([
                'family_group_id' => $groupId,
                'created_by'      => $user->id,
                'period_type'     => $input['period_type'],
                'start_date'      => $input['start_date'],
                'end_date'        => $input['end_date'],
                'mode'            => 'auto',
                'status'          => 'pending',
            ], $items);
        });

        $this->genRepo->saveSuggestions($groupId, $plan->id, $candidates);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_generated',
            'entity_name' => 'meal_plans',
            'entity_id'   => $plan->id,
            'old_values'  => null,
            'new_values'  => [
                'family_group_id' => $groupId,
                'period_type'     => $plan->period_type,
                'start_date'      => $input['start_date'],
                'end_date'        => $input['end_date'],
            ],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $plan;
    }

    public function approve(User $user, int $groupId, int $planId, string $ip, string $ua): MealPlan
    {
        $this->assertMember($user, $groupId);

        $plan = DB::transaction(function () use ($planId, $groupId) {
            $locked = $this->genRepo->lockPlanForUpdate($planId, $groupId);

            if (!$locked) {
                throw MealPlanGenerationException::notFound();
            }
            if ($locked->status === 'approved') {
                throw MealPlanGenerationException::alreadyApproved();
            }
            if ($locked->status !== 'pending') {
                throw MealPlanGenerationException::cannotApprove();
            }

            return $this->genRepo->approvePlan($locked);
        });

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_approved',
            'entity_name' => 'meal_plans',
            'entity_id'   => $plan->id,
            'old_values'  => ['status' => 'pending'],
            'new_values'  => ['status' => 'approved'],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $plan;
    }

    public function regenerate(User $user, int $groupId, int $planId, array $input, string $ip, string $ua): MealPlan
    {
        $this->assertMember($user, $groupId);

        $plan = $this->planRepo->findForGroup($planId, $groupId);
        if (!$plan) {
            throw MealPlanGenerationException::notFound();
        }
        if ($plan->status === 'approved') {
            throw MealPlanGenerationException::cannotRegenerate();
        }

        $mealTypes  = $this->genRepo->activeMealTypes();
        $pref       = $this->genRepo->groupPreference($groupId);
        $avoidRep   = $pref ? (bool) $pref->avoid_repetition : true;
        $respBudget = $pref ? (bool) $pref->respect_budget   : false;

        $allowedIds = null;
        if ($respBudget) {
            $budget = $this->suggRepo->currentBudget($groupId);
            if ($budget && $budget->total_amount > 0) {
                $ids = $this->suggRepo->recipeIdsByMaxCost($groupId, (float) $budget->total_amount);
                if (!empty($ids)) {
                    $allowedIds = $ids;
                }
            }
        }

        $candidates = $this->genRepo->candidateRecipesForGroup($groupId, $allowedIds);
        if (empty($candidates)) {
            throw MealPlanGenerationException::noRecipesAvailable();
        }

        $startDate = $input['start_date'] ?? $plan->start_date->format('Y-m-d');
        $endDate   = $input['end_date']   ?? $plan->end_date->format('Y-m-d');

        $items = $this->buildItems($candidates, $mealTypes->all(), $startDate, $endDate, $avoidRep);

        $plan = DB::transaction(function () use ($plan, $items) {
            return $this->genRepo->replaceItems($plan, $items);
        });

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_regenerated',
            'entity_name' => 'meal_plans',
            'entity_id'   => $plan->id,
            'old_values'  => null,
            'new_values'  => ['regenerated' => true],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $plan;
    }

    private function assertMember(User $user, int $groupId): void
    {
        try {
            $this->groupRepo->findOrFailForUser($groupId, $user->id);
        } catch (FamilyGroupException $e) {
            throw MealPlanGenerationException::groupNotFound();
        }
    }

    private function buildItems(array $candidates, array $mealTypes, string $startDate, string $endDate, bool $avoidRep): array
    {
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        $items    = [];
        $usedIds  = [];
        $poolLen  = count($candidates);
        $cycleIdx = 0;

        $current = new \DateTime($startDate);
        $end     = new \DateTime($endDate);

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');

            foreach ($mealTypes as $mealType) {
                $recipe = null;

                if ($poolLen > 0) {
                    if ($avoidRep) {
                        foreach ($candidates as $c) {
                            if (!in_array($c['id'], $usedIds)) {
                                $recipe    = $c;
                                $usedIds[] = $c['id'];
                                break;
                            }
                        }
                        if (!$recipe) {
                            $recipe = $candidates[$cycleIdx % $poolLen];
                            $cycleIdx++;
                        }
                    } else {
                        $recipe = $candidates[$cycleIdx % $poolLen];
                        $cycleIdx++;
                    }
                }

                $items[] = [
                    'date'                  => $dateStr,
                    'meal_type_id'          => $mealType->id,
                    'recipe_id'             => $recipe['id'] ?? null,
                    'free_meal_description' => $recipe ? null : 'Sin asignar',
                    'is_eating_out'         => false,
                    'status'                => 'planned',
                ];
            }

            $current->modify('+1 day');
        }

        return $items;
    }
}
