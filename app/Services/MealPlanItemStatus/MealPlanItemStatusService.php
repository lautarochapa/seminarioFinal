<?php

namespace App\Services\MealPlanItemStatus;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Exceptions\MealPlanItems\MealPlanItemException;
use App\MealPlanItem;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\MealPlanItems\MealPlanItemRepository;
use App\Repositories\MealPlanItemStatus\MealPlanItemStatusRepository;
use App\Repositories\RecipeFavoritesCooked\RecipeFavoritesCookedRepository;
use App\User;
use Illuminate\Support\Facades\DB;

class MealPlanItemStatusService
{
    private $groups;
    private $items;
    private $statusRepo;
    private $cookedRepo;

    public function __construct(
        FamilyGroupRepository $groups,
        MealPlanItemRepository $items,
        MealPlanItemStatusRepository $statusRepo,
        RecipeFavoritesCookedRepository $cookedRepo
    ) {
        $this->groups = $groups;
        $this->items = $items;
        $this->statusRepo = $statusRepo;
        $this->cookedRepo = $cookedRepo;
    }

    public function markCooked(User $user, int $groupId, int $planId, int $itemId, array $input, string $ip, string $ua): MealPlanItem
    {
        $this->assertMember($user, $groupId);
        $this->assertPlanForGroup($planId, $groupId);
        $item = $this->assertItemForPlan($itemId, $planId);
        $this->assertNotFinalized($item);

        if (!$item->recipe_id) {
            throw MealPlanItemException::recipeRequired();
        }

        $servings = $this->servings($item, $input);
        $recipe = $this->cookedRepo->loadRecipeWithIngredients((int) $item->recipe_id);

        if (!$recipe) {
            throw MealPlanItemException::recipeNotFound((int) $item->recipe_id);
        }

        return DB::transaction(function () use ($user, $groupId, $item, $recipe, $servings, $input, $ip, $ua) {
            $old = $this->payload($item);
            $deductions = $this->planDeductions($groupId, $recipe, $servings);

            $log = $this->statusRepo->createCookLog([
                'user_id' => $user->id,
                'family_group_id' => $groupId,
                'recipe_id' => $recipe->id,
                'meal_plan_item_id' => $item->id,
                'servings' => (int) ceil($servings),
                'cooked_at' => now(),
                'stock_discounted' => false,
                'notes' => $input['notes'] ?? null,
            ]);

            foreach ($deductions as $deduction) {
                $this->cookedRepo->deductStockItem($deduction['item_id'], $deduction['deduct']);
                $this->cookedRepo->createStockMovement([
                    'family_group_id' => $groupId,
                    'stock_item_id' => $deduction['item_id'],
                    'product_id' => $deduction['product_id'],
                    'movement_type' => 'recipe_consumption',
                    'quantity' => -1 * $deduction['deduct'],
                    'unit_id' => $deduction['unit_id'],
                    'reason' => 'meal_plan_item_cooked',
                    'related_recipe_id' => $recipe->id,
                    'related_meal_plan_item_id' => $item->id,
                    'created_by' => $user->id,
                    'created_at' => now(),
                ]);
            }

            if (count($deductions) > 0) {
                $this->cookedRepo->markCookLogDiscounted($log->id);
            }

            $this->statusRepo->createConsumptionLog([
                'meal_plan_item_id' => $item->id,
                'user_id' => $user->id,
                'consumed' => true,
                'portion_factor' => 1,
                'notes' => $input['notes'] ?? null,
            ]);

            $updated = $this->statusRepo->update($item, [
                'status' => 'cooked',
                'is_eating_out' => false,
            ]);

            $this->audit($user->id, 'meal_plan_item_cooked', $item->id, $old, $this->payload($updated), $ip, $ua);

            return $updated;
        });
    }

    public function skip(User $user, int $groupId, int $planId, int $itemId, array $input, string $ip, string $ua): MealPlanItem
    {
        $this->assertMember($user, $groupId);
        $this->assertPlanForGroup($planId, $groupId);
        $item = $this->assertItemForPlan($itemId, $planId);
        $this->assertNotFinalized($item);

        return DB::transaction(function () use ($user, $item, $input, $ip, $ua) {
            $old = $this->payload($item);
            $eatingOut = (bool) ($input['eating_out'] ?? false);
            $status = $eatingOut ? 'eating_out' : 'skipped';

            $this->statusRepo->createConsumptionLog([
                'meal_plan_item_id' => $item->id,
                'user_id' => $user->id,
                'consumed' => false,
                'portion_factor' => 1,
                'notes' => $input['notes'] ?? null,
            ]);

            $updated = $this->statusRepo->update($item, [
                'status' => $status,
                'is_eating_out' => $eatingOut,
            ]);

            $this->audit($user->id, $eatingOut ? 'meal_plan_item_eating_out' : 'meal_plan_item_skipped', $item->id, $old, $this->payload($updated), $ip, $ua);

            return $updated;
        });
    }

    private function assertMember(User $user, int $groupId): void
    {
        try {
            $this->groups->findOrFailForUser($groupId, $user->id);
        } catch (FamilyGroupException $e) {
            throw MealPlanItemException::groupNotFound();
        }
    }

    private function assertPlanForGroup(int $planId, int $groupId): void
    {
        if (!$this->items->planExistsForGroup($planId, $groupId)) {
            throw MealPlanItemException::planNotFound();
        }
    }

    private function assertItemForPlan(int $itemId, int $planId): MealPlanItem
    {
        $item = $this->statusRepo->findForPlan($itemId, $planId);
        if (!$item) {
            throw MealPlanItemException::notFound();
        }

        return $item;
    }

    private function assertNotFinalized(MealPlanItem $item): void
    {
        if (in_array($item->status, ['cooked', 'skipped', 'eating_out', 'cancelled'], true)) {
            throw MealPlanItemException::alreadyFinalized();
        }
    }

    private function servings(MealPlanItem $item, array $input): float
    {
        if (isset($input['servings'])) {
            return (float) $input['servings'];
        }

        if ($item->servings_total !== null && (float) $item->servings_total > 0) {
            return (float) $item->servings_total;
        }

        if ($item->relationLoaded('portions') && $item->portions->count() > 0) {
            $sum = (float) $item->portions->sum(function ($portion) {
                return $portion->servings !== null ? (float) $portion->servings : (float) $portion->portion_factor;
            });

            if ($sum > 0) {
                return $sum;
            }
        }

        if ($item->recipe && $item->recipe->servings !== null && (float) $item->recipe->servings > 0) {
            return (float) $item->recipe->servings;
        }

        return 1.0;
    }

    private function planDeductions(int $groupId, $recipe, float $servings): array
    {
        $baseServings = ($recipe->servings !== null && (float) $recipe->servings > 0) ? (float) $recipe->servings : 1.0;
        $deductions = [];

        foreach ($recipe->ingredients as $recipeIngredient) {
            if ($recipeIngredient->is_optional) {
                continue;
            }

            $ingredientId = (int) $recipeIngredient->ingredient_id;
            $recipeUnitId = (int) $recipeIngredient->unit_id;
            $totalRequired = ((float) $recipeIngredient->quantity / $baseServings) * $servings;
            $remaining = $totalRequired;
            $stockItems = $this->cookedRepo->stockItemsForIngredient($groupId, $ingredientId);

            foreach ($stockItems as $stockItem) {
                if ($remaining <= 0) {
                    break;
                }

                $factor = $this->cookedRepo->findConversionFactor((int) $stockItem->unit_id, $recipeUnitId, $ingredientId);
                if ($factor === null) {
                    continue;
                }

                $availableRecipeUnit = (float) $stockItem->quantity * $factor;
                $deductRecipeUnit = min($remaining, $availableRecipeUnit);
                $deductStockUnit = $deductRecipeUnit / $factor;

                $deductions[] = [
                    'item_id' => (int) $stockItem->id,
                    'deduct' => $deductStockUnit,
                    'unit_id' => (int) $stockItem->unit_id,
                    'product_id' => (int) $stockItem->product_id,
                ];

                $remaining -= $deductRecipeUnit;
            }

            if ($remaining > 0.0001) {
                throw MealPlanItemException::insufficientStock();
            }
        }

        return $deductions;
    }

    private function payload(MealPlanItem $item): array
    {
        return [
            'meal_plan_id' => $item->meal_plan_id,
            'recipe_id' => $item->recipe_id,
            'status' => $item->status,
            'is_eating_out' => (bool) $item->is_eating_out,
            'servings_total' => $item->servings_total,
        ];
    }

    private function audit(int $userId, string $action, int $itemId, array $old, array $new, string $ip, string $ua): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_name' => 'meal_plan_items',
            'entity_id' => (string) $itemId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $ip,
            'user_agent' => $ua,
        ]);
    }
}
