<?php

namespace App\Services\ShoppingListGeneration;

use App\AuditLog;
use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ShoppingListGeneration\ShoppingListGenerationRepository;
use App\Services\ShoppingListPreview\ShoppingListPreviewService;
use App\User;
use Illuminate\Support\Facades\DB;

class ShoppingListGenerationService
{
    private $groups;
    private $historyRepo;
    private $mealPlanGenerator;

    public function __construct(
        FamilyGroupRepository $groups,
        ShoppingListGenerationRepository $historyRepo,
        ShoppingListPreviewService $mealPlanGenerator
    ) {
        $this->groups = $groups;
        $this->historyRepo = $historyRepo;
        $this->mealPlanGenerator = $mealPlanGenerator;
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
            $list = $this->historyRepo->existingHistoryList($groupId);
            $created = false;

            if (!$list) {
                $list = $this->historyRepo->createHistoryList($groupId, $user->id);
                $created = true;
            }

            $this->historyRepo->replaceItems($list, $items);
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
