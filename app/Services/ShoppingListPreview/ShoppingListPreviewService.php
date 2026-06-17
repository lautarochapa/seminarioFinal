<?php

namespace App\Services\ShoppingListPreview;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Exceptions\MealPlanItems\MealPlanItemException;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ShoppingListPreview\ShoppingListPreviewRepository;
use App\ShoppingList;
use App\User;
use Illuminate\Support\Facades\DB;

class ShoppingListPreviewService
{
    private $groups;
    private $repo;

    public function __construct(FamilyGroupRepository $groups, ShoppingListPreviewRepository $repo)
    {
        $this->groups = $groups;
        $this->repo = $repo;
    }

    public function preview(User $user, int $groupId, int $planId): array
    {
        $this->assertMember($user, $groupId);
        $plan = $this->repo->findPlanForGroup($planId, $groupId);

        if (!$plan) {
            throw MealPlanItemException::planNotFound();
        }

        $requirements = $this->requirements($plan);
        return $this->subtractStock($groupId, $requirements);
    }

    public function generate(User $user, int $groupId, int $planId, string $ip, string $ua): array
    {
        $items = $this->preview($user, $groupId, $planId);

        return DB::transaction(function () use ($user, $groupId, $planId, $items, $ip, $ua) {
            $existing = $this->repo->existingList($groupId, $planId);
            $created = false;

            if ($existing) {
                $list = $existing;
            } else {
                $list = $this->repo->createList([
                    'family_group_id' => $groupId,
                    'meal_plan_id' => $planId,
                    'created_by' => $user->id,
                    'source_type' => 'meal_plan',
                    'status' => 'draft',
                ]);
                $created = true;
            }

            $this->repo->replaceItems($list, $items);
            $list = $this->repo->loadList($list);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'shopping_list.generated',
                'entity_name' => 'shopping_lists',
                'entity_id' => (string) $list->id,
                'old_values' => null,
                'new_values' => [
                    'family_group_id' => $groupId,
                    'meal_plan_id' => $planId,
                    'items_count' => count($items),
                    'created' => $created,
                ],
                'ip_address' => $ip,
                'user_agent' => $ua,
            ]);

            return ['list' => $list, 'created' => $created];
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

    private function requirements($plan): array
    {
        $requirements = [];

        foreach ($plan->items as $item) {
            if (!$item->recipe) {
                continue;
            }

            $servings = $this->servings($item);
            $baseServings = ($item->recipe->servings !== null && (float) $item->recipe->servings > 0)
                ? (float) $item->recipe->servings
                : 1.0;

            foreach ($item->recipe->ingredients as $recipeIngredient) {
                if ($recipeIngredient->is_optional || !$recipeIngredient->ingredient || !$recipeIngredient->unit) {
                    continue;
                }

                $key = $recipeIngredient->ingredient_id.'-'.$recipeIngredient->unit_id;
                $quantity = ((float) $recipeIngredient->quantity / $baseServings) * $servings;

                if (!isset($requirements[$key])) {
                    $requirements[$key] = [
                        'ingredient' => [
                            'id' => (int) $recipeIngredient->ingredient->id,
                            'name' => $recipeIngredient->ingredient->name,
                        ],
                        'unit' => [
                            'id' => (int) $recipeIngredient->unit->id,
                            'code' => $recipeIngredient->unit->code,
                            'symbol' => $recipeIngredient->unit->symbol,
                        ],
                        'required_quantity' => 0.0,
                        'recipe_sources' => [],
                        'incomplete' => false,
                    ];
                }

                $requirements[$key]['required_quantity'] += $quantity;
                $requirements[$key]['recipe_sources'][] = [
                    'meal_plan_item_id' => (int) $item->id,
                    'recipe_id' => (int) $item->recipe->id,
                    'recipe_name' => $item->recipe->nombre,
                    'quantity' => $quantity,
                ];
            }
        }

        return array_values($requirements);
    }

    private function servings($item): float
    {
        if ($item->servings_total !== null && (float) $item->servings_total > 0) {
            return (float) $item->servings_total;
        }

        if ($item->recipe && $item->recipe->servings !== null && (float) $item->recipe->servings > 0) {
            return (float) $item->recipe->servings;
        }

        return 1.0;
    }

    private function subtractStock(int $groupId, array $requirements): array
    {
        $missing = [];

        foreach ($requirements as $requirement) {
            $remaining = (float) $requirement['required_quantity'];
            $ingredientId = (int) $requirement['ingredient']['id'];
            $unitId = (int) $requirement['unit']['id'];
            $stockItems = $this->repo->stockItemsForIngredient($groupId, $ingredientId);
            $incomplete = false;

            foreach ($stockItems as $stockItem) {
                if ($remaining <= 0) {
                    break;
                }

                $factor = $this->repo->conversionFactor((int) $stockItem['unit_id'], $unitId, $ingredientId);
                if ($factor === null) {
                    $incomplete = true;
                    continue;
                }

                $remaining -= min($remaining, (float) $stockItem['quantity'] * $factor);
            }

            if ($remaining > 0.0001) {
                $requirement['missing_quantity'] = round($remaining, 4);
                $requirement['incomplete'] = $requirement['incomplete'] || $incomplete;
                $missing[] = $requirement;
            }
        }

        return $missing;
    }
}
