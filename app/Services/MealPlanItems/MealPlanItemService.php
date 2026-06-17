<?php

namespace App\Services\MealPlanItems;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Exceptions\MealPlanItems\MealPlanItemException;
use App\MealPlanItem;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\MealPlanItems\MealPlanItemRepository;
use App\Repositories\MealPlans\MealPlanRepository;
use App\User;
use Illuminate\Support\Collection;

class MealPlanItemService
{
    private MealPlanItemRepository $itemRepo;
    private MealPlanRepository     $planRepo;
    private FamilyGroupRepository  $groupRepo;

    public function __construct(
        MealPlanItemRepository $itemRepo,
        MealPlanRepository     $planRepo,
        FamilyGroupRepository  $groupRepo
    ) {
        $this->itemRepo  = $itemRepo;
        $this->planRepo  = $planRepo;
        $this->groupRepo = $groupRepo;
    }

    public function list(User $user, int $groupId, int $planId): Collection
    {
        $this->assertMember($user, $groupId);
        $this->assertPlanForGroup($planId, $groupId);
        return $this->itemRepo->listForPlan($planId);
    }

    public function create(User $user, int $groupId, int $planId, array $input, string $ip, string $ua): MealPlanItem
    {
        $this->assertMember($user, $groupId);
        $this->assertPlanForGroup($planId, $groupId);
        $this->assertContent($input);

        $mealTypeId = (int) $input['meal_type_id'];
        if (!$this->planRepo->findActiveMealType($mealTypeId)) {
            throw MealPlanItemException::mealTypeNotFound($mealTypeId);
        }

        $recipeId = null;
        if (!empty($input['recipe_id'])) {
            $recipeId = (int) $input['recipe_id'];
            if (!$this->planRepo->findActiveRecipe($recipeId)) {
                throw MealPlanItemException::recipeNotFound($recipeId);
            }
        }

        if ($this->itemRepo->duplicateExists($planId, $input['date'], $mealTypeId)) {
            throw MealPlanItemException::duplicateItem();
        }

        $item = $this->itemRepo->create([
            'meal_plan_id'          => $planId,
            'date'                  => $input['date'],
            'meal_type_id'          => $mealTypeId,
            'recipe_id'             => $recipeId,
            'free_meal_description' => $input['free_meal_description'] ?? null,
            'is_eating_out'         => (bool) ($input['is_eating_out'] ?? false),
            'servings_total'        => isset($input['servings_total']) ? (float) $input['servings_total'] : null,
            'notes'                 => $input['notes'] ?? null,
            'status'                => 'planned',
        ]);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_item_created',
            'entity_name' => 'meal_plan_items',
            'entity_id'   => $item->id,
            'old_values'  => null,
            'new_values'  => ['meal_plan_id' => $planId, 'date' => $input['date'], 'meal_type_id' => $mealTypeId],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $item;
    }

    public function update(User $user, int $groupId, int $planId, int $itemId, array $input, string $ip, string $ua): MealPlanItem
    {
        $this->assertMember($user, $groupId);
        $this->assertPlanForGroup($planId, $groupId);
        $item = $this->assertItemForPlan($itemId, $planId);

        if (isset($input['meal_type_id']) || isset($input['date'])) {
            $newMealTypeId = isset($input['meal_type_id']) ? (int) $input['meal_type_id'] : $item->meal_type_id;
            $newDate       = $input['date'] ?? $item->date->format('Y-m-d');

            if ($this->itemRepo->duplicateExists($planId, $newDate, $newMealTypeId, $itemId)) {
                throw MealPlanItemException::duplicateItem();
            }
        }

        if (isset($input['meal_type_id'])) {
            $mealTypeId = (int) $input['meal_type_id'];
            if (!$this->planRepo->findActiveMealType($mealTypeId)) {
                throw MealPlanItemException::mealTypeNotFound($mealTypeId);
            }
        }

        if (isset($input['recipe_id']) && !empty($input['recipe_id'])) {
            $recipeId = (int) $input['recipe_id'];
            if (!$this->planRepo->findActiveRecipe($recipeId)) {
                throw MealPlanItemException::recipeNotFound($recipeId);
            }
        }

        $allowed = ['date', 'meal_type_id', 'recipe_id', 'free_meal_description', 'is_eating_out', 'servings_total', 'notes'];
        $fields  = array_intersect_key($input, array_flip($allowed));
        $old     = $item->only(array_keys($fields));

        $updated = $this->itemRepo->update($item, $fields);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_item_updated',
            'entity_name' => 'meal_plan_items',
            'entity_id'   => $item->id,
            'old_values'  => $old,
            'new_values'  => $fields,
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $updated;
    }

    public function delete(User $user, int $groupId, int $planId, int $itemId, string $ip, string $ua): void
    {
        $this->assertMember($user, $groupId);
        $this->assertPlanForGroup($planId, $groupId);
        $item = $this->assertItemForPlan($itemId, $planId);

        $this->itemRepo->delete($item);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_item_deleted',
            'entity_name' => 'meal_plan_items',
            'entity_id'   => $item->id,
            'old_values'  => ['date' => $item->date, 'meal_type_id' => $item->meal_type_id],
            'new_values'  => ['deleted' => true],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);
    }

    private function assertMember(User $user, int $groupId): void
    {
        try {
            $this->groupRepo->findOrFailForUser($groupId, $user->id);
        } catch (FamilyGroupException $e) {
            throw MealPlanItemException::groupNotFound();
        }
    }

    private function assertPlanForGroup(int $planId, int $groupId): void
    {
        if (!$this->itemRepo->planExistsForGroup($planId, $groupId)) {
            throw MealPlanItemException::planNotFound();
        }
    }

    private function assertItemForPlan(int $itemId, int $planId): MealPlanItem
    {
        $item = $this->itemRepo->findForPlan($itemId, $planId);
        if (!$item) {
            throw MealPlanItemException::notFound();
        }
        return $item;
    }

    private function assertContent(array $input): void
    {
        $hasRecipe   = !empty($input['recipe_id']);
        $hasFree     = !empty($input['free_meal_description']);
        $isEatingOut = !empty($input['is_eating_out']);

        if (!$hasRecipe && !$hasFree && !$isEatingOut) {
            throw MealPlanItemException::missingContent();
        }
    }
}
