<?php

namespace App\Services\ShoppingListGeneration;

use App\AuditLog;
use App\FamilyGroup;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ShoppingListGeneration\ShoppingListGenerationRepository;
use App\Services\ShoppingListPreview\ShoppingListPreviewService;
use App\Services\ShoppingLists\ShoppingListGenerationGuard;
use App\Services\ShoppingLists\ShoppingListTotalService;
use App\User;
use Illuminate\Support\Facades\DB;

class ShoppingListGenerationService
{
    private $groups;
    private $historyRepo;
    private $mealPlanGenerator;
    private $totals;
    private $generationGuard;

    public function __construct(
        FamilyGroupRepository $groups,
        ShoppingListGenerationRepository $historyRepo,
        ShoppingListPreviewService $mealPlanGenerator,
        ShoppingListTotalService $totals,
        ShoppingListGenerationGuard $generationGuard
    ) {
        $this->groups = $groups;
        $this->historyRepo = $historyRepo;
        $this->mealPlanGenerator = $mealPlanGenerator;
        $this->totals = $totals;
        $this->generationGuard = $generationGuard;
    }

    public function fromMealPlan(User $user, int $groupId, int $mealPlanId, string $ip, string $ua): array
    {
        return $this->mealPlanGenerator->generate($user, $groupId, $mealPlanId, $ip, $ua);
    }

    public function fromHistory(User $user, int $groupId, array $filters, string $ip, string $ua): array
    {
        $this->groups->findOrFailForUser($groupId, $user->id);
        $items = $this->itemsFromHistory($groupId, $filters);

        if (count($items) === 0) {
            throw new FamilyGroupException('SHOPPING_LIST_HISTORY_INSUFFICIENT', 'No hay historial suficiente para generar la lista.', 409);
        }

        return DB::transaction(function () use ($user, $groupId, $items, $ip, $ua) {
            // NO KEY UPDATE permits purchases' FK checks while serializing history generators.
            FamilyGroup::where('id', $groupId)
                ->lock(DB::connection()->getDriverName() === 'pgsql' ? 'for no key update' : true)->firstOrFail();
            $list = $this->historyRepo->existingHistoryList($groupId);
            if ($list) $this->generationGuard->assertReusable($list);
            $created = false;

            if (!$list) {
                $list = $this->historyRepo->createHistoryList($groupId, $user->id);
                $created = true;
            } elseif ($list->status !== \App\ShoppingList::STATUS_ACTIVE && $list->canTransitionTo(\App\ShoppingList::STATUS_ACTIVE)) {
                $list->status = \App\ShoppingList::STATUS_ACTIVE;
                $list->save();
            }

            $this->historyRepo->replaceItems($list, $items);
            $this->totals->recalculate($list);
            $list = $this->historyRepo->loadList($list);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'shopping_list.generated',
                'entity_name' => 'shopping_lists',
                'entity_id' => (string) $list->id,
                'old_values' => null,
                'new_values' => [
                    'family_group_id' => $groupId,
                    'source_type' => 'history',
                    'items_count' => count($items),
                    'created' => $created,
                ],
                'ip_address' => $ip,
                'user_agent' => $ua,
            ]);

            return ['list' => $list, 'created' => $created];
        });
    }

    private function itemsFromHistory(int $groupId, array $filters): array
    {
        $rows = $this->historyRepo->historyRows($groupId, $filters);
        $grouped = [];

        foreach ($rows as $row) {
            $key = $row['ingredient_id'].'-'.$row['unit_id'];

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'ingredient_id' => (int) $row['ingredient_id'],
                    'unit_id' => (int) $row['unit_id'],
                    'quantity' => 0.0,
                ];
            }

            $grouped[$key]['quantity'] += (float) $row['quantity'];
        }

        return array_values($grouped);
    }
}
