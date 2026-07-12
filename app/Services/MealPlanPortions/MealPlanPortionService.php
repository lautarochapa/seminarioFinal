<?php

namespace App\Services\MealPlanPortions;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Exceptions\MealPlanPortions\MealPlanPortionException;
use App\MealPlanItemPortion;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\MealPlanPortions\MealPlanPortionRepository;
use App\User;
use Illuminate\Support\Collection;

class MealPlanPortionService
{
    private MealPlanPortionRepository $repo;
    private FamilyGroupRepository     $groupRepo;

    public function __construct(MealPlanPortionRepository $repo, FamilyGroupRepository $groupRepo)
    {
        $this->repo      = $repo;
        $this->groupRepo = $groupRepo;
    }

    public function list(User $user, int $groupId, int $planId, int $itemId): Collection
    {
        $this->assertMember($user, $groupId);
        $this->assertItemContext($itemId, $planId, $groupId);
        return $this->repo->listForItem($itemId);
    }

    public function create(User $user, int $groupId, int $planId, int $itemId, array $input, string $ip, string $ua): MealPlanItemPortion
    {
        $this->assertMember($user, $groupId);
        $this->assertItemContext($itemId, $planId, $groupId);

        $userId = (int) $input['user_id'];
        if (!$this->repo->memberBelongsToGroup($userId, $groupId)) {
            throw MealPlanPortionException::memberNotInGroup();
        }

        if ($this->repo->duplicateExists($itemId, $userId)) {
            throw MealPlanPortionException::duplicatePortion();
        }

        $portion = $this->repo->create([
            'meal_plan_item_id' => $itemId,
            'user_id'           => $userId,
            'portion_factor'    => isset($input['portion_factor']) ? (float) $input['portion_factor'] : 1.0,
            'servings'          => isset($input['servings']) ? (float) $input['servings'] : null,
            'notes'             => $input['notes'] ?? null,
        ]);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_portion_created',
            'entity_name' => 'meal_plan_item_portions',
            'entity_id'   => $portion->id,
            'old_values'  => null,
            'new_values'  => ['meal_plan_item_id' => $itemId, 'user_id' => $userId],
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $portion;
    }

    public function update(User $user, int $groupId, int $planId, int $itemId, int $portionId, array $input, string $ip, string $ua): MealPlanItemPortion
    {
        $this->assertMember($user, $groupId);
        $this->assertItemContext($itemId, $planId, $groupId);

        $portion = $this->repo->findForItem($portionId, $itemId);
        if (!$portion) {
            throw MealPlanPortionException::notFound();
        }

        $allowed = ['portion_factor', 'servings', 'notes'];
        $fields  = array_intersect_key($input, array_flip($allowed));
        $old     = $portion->only(array_keys($fields));

        $updated = $this->repo->update($portion, $fields);

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'meal_plan_portion_updated',
            'entity_name' => 'meal_plan_item_portions',
            'entity_id'   => $portion->id,
            'old_values'  => $old,
            'new_values'  => $fields,
            'ip_address'  => $ip,
            'user_agent'  => $ua,
        ]);

        return $updated;
    }

    private function assertMember(User $user, int $groupId): void
    {
        try {
            $this->groupRepo->findOrFailForUser($groupId, $user->id);
        } catch (FamilyGroupException $e) {
            throw MealPlanPortionException::groupNotFound();
        }
    }

    private function assertItemContext(int $itemId, int $planId, int $groupId): void
    {
        if (!$this->repo->itemExistsForContext($itemId, $planId, $groupId)) {
            throw MealPlanPortionException::itemNotFound();
        }
    }
}
