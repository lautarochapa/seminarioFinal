<?php

namespace App\Services\UserHome;

use App\Services\RecipeSuggestions\RecipeSuggestionsService;
use App\User;
use Illuminate\Support\Facades\DB;

class UserHomeSummaryService
{
    private $recipeSuggestionsService;

    public function __construct(RecipeSuggestionsService $recipeSuggestionsService)
    {
        $this->recipeSuggestionsService = $recipeSuggestionsService;
    }

    public function summary(User $user, ?int $groupId): array
    {
        $groupId = $groupId ?: $this->defaultGroupId($user);

        if (!$groupId || !$this->isMember($user->id, $groupId)) {
            return [
                'family_group_id' => null,
                'stock' => ['products' => 0, 'low_stock' => 0, 'expiring' => 0, 'expired' => 0],
                'recipes' => ['available' => 0],
                'shopping' => ['active_lists' => 0, 'pending_items' => 0],
                'actions' => [],
            ];
        }

        $today = now()->toDateString();
        $soon = now()->addDays(7)->toDateString();

        $products = DB::table('stock_items')
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('quantity', '>', 0)
            ->distinct('product_id')
            ->count('product_id');

        // Bajo stock: se calcula igual que el endpoint GET /stock/low-stock
        // (join con stock_minimum_rules por producto), no desde stock_alerts, que
        // solo se puebla para vencimientos ya procesados.
        $lowStock = DB::table('stock_items')
            ->join('stock_minimum_rules', function ($join) {
                $join->on('stock_minimum_rules.product_id', '=', 'stock_items.product_id')
                    ->on('stock_minimum_rules.family_group_id', '=', 'stock_items.family_group_id');
            })
            ->where('stock_items.family_group_id', $groupId)
            ->where('stock_items.status', 'active')
            ->whereNull('stock_items.deleted_at')
            ->where('stock_minimum_rules.status', 'active')
            ->whereColumn('stock_items.quantity', '<', 'stock_minimum_rules.minimum_quantity')
            ->distinct('stock_items.id')
            ->count('stock_items.id');

        $expiring = DB::table('stock_items')
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereBetween('expiration_date', [$today, $soon])
            ->count();

        $expired = DB::table('stock_items')
            ->where('family_group_id', $groupId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', $today)
            ->count();

        $activeLists = DB::table('shopping_lists')
            ->where('family_group_id', $groupId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['draft', 'active'])
            ->count();

        $pendingItems = DB::table('shopping_list_items as sli')
            ->join('shopping_lists as sl', 'sl.id', '=', 'sli.shopping_list_id')
            ->where('sl.family_group_id', $groupId)
            ->whereNull('sl.deleted_at')
            ->where('sli.status', 'pending')
            ->count();

        return [
            'family_group_id' => $groupId,
            'stock' => [
                'products' => $products,
                'low_stock' => $lowStock,
                'expiring' => $expiring,
                'expired' => $expired,
            ],
            'recipes' => [
                'available' => $this->availableRecipes($groupId, $user),
            ],
            'shopping' => [
                'active_lists' => $activeLists,
                'pending_items' => $pendingItems,
            ],
            'actions' => $this->actions($products, $activeLists, $pendingItems, $expired, $expiring, $lowStock),
        ];
    }

    private function defaultGroupId(User $user): ?int
    {
        $id = DB::table('family_group_members')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByRaw("CASE WHEN role_in_group = 'owner' THEN 0 ELSE 1 END")
            ->value('family_group_id');

        return $id ? (int) $id : null;
    }

    private function isMember(int $userId, int $groupId): bool
    {
        return DB::table('family_group_members')
            ->where('family_group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    private function availableRecipes(int $groupId, User $user): int
    {
        $available = $this->recipeSuggestionsService->available($user, $groupId, 1, 1);

        return (int) $available['meta']['total'];
    }

    private function actions(int $products, int $activeLists, int $pendingItems, int $expired, int $expiring, int $lowStock = 0): array
    {
        $actions = [];
        if ($products === 0) {
            $actions[] = ['type' => 'empty_stock', 'message' => 'Todavia no cargaste productos en tu cocina.'];
        }
        if ($lowStock > 0) {
            $actions[] = ['type' => 'low_stock', 'message' => 'Tenes productos por debajo de tu stock minimo.'];
        }
        if ($expired > 0) {
            $actions[] = ['type' => 'expired_stock', 'message' => 'Tenes productos vencidos para revisar.'];
        }
        if ($expiring > 0) {
            $actions[] = ['type' => 'expiring_stock', 'message' => 'Hay productos por vencer esta semana.'];
        }
        if ($activeLists > 0 && $pendingItems > 0) {
            $actions[] = ['type' => 'shopping_list', 'message' => 'Tenes una lista de compras pendiente.'];
        }

        return $actions;
    }
}
