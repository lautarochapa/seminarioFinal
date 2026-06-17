<?php

namespace App\Services\SupermarketComparison;

use App\Exceptions\FamilyGroup\FamilyGroupException;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ShoppingLists\ShoppingListRepository;
use App\Repositories\SupermarketComparison\SupermarketComparisonRepository;
use App\ShoppingList;
use App\User;

class SupermarketComparisonService
{
    private $groups;
    private $lists;
    private $comparison;

    public function __construct(FamilyGroupRepository $groups, ShoppingListRepository $lists, SupermarketComparisonRepository $comparison)
    {
        $this->groups = $groups;
        $this->lists = $lists;
        $this->comparison = $comparison;
    }

    public function compare(User $user, int $groupId, int $listId): array
    {
        $this->assertMember($user, $groupId);
        $list = $this->findList($groupId, $listId);
        $items = $list->items;
        $branches = [];

        foreach ($this->comparison->activeBranches() as $branch) {
            $found = [];
            $missing = [];
            $promotions = [];
            $total = 0.0;
            $currency = null;
            $lastPriceAt = null;

            foreach ($items as $item) {
                if (!$item->product_id || !$item->unit_id) {
                    $missing[] = $this->missingItem($item);
                    continue;
                }

                $price = $this->comparison->latestPriceForBranch($branch->id, (int) $item->product_id, (int) $item->unit_id);
                if (!$price) {
                    $missing[] = $this->missingItem($item);
                    continue;
                }

                if ($currency !== null && $currency !== $price->currency) {
                    $missing[] = $this->missingItem($item);
                    continue;
                }

                $currency = $currency ?: $price->currency;
                $lineTotal = (float) $price->price * (float) $item->quantity;
                $effectiveLineTotal = $this->applyPromotion($lineTotal, $price->promotion);
                $total += $effectiveLineTotal;
                $lastPriceAt = $price->scraped_at;

                $found[] = [
                    'item_id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                    ],
                    'quantity' => $item->quantity,
                    'unit_price' => $price->price,
                    'total' => round($effectiveLineTotal, 2),
                    'currency' => $price->currency,
                ];

                if ($price->promotion) {
                    $promotions[$price->promotion->id] = [
                        'id' => $price->promotion->id,
                        'name' => $price->promotion->name,
                    ];
                }
            }

            $branches[] = [
                'branch' => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'chain' => $branch->chain ? $branch->chain->name : null,
                ],
                'total' => round($total, 2),
                'currency' => $currency,
                'found_count' => count($found),
                'missing_count' => count($missing),
                'coverage' => $items->count() > 0 ? count($found) / $items->count() : 0,
                'items' => $found,
                'missing_items' => $missing,
                'promotions' => array_values($promotions),
                'last_price_at' => optional($lastPriceAt)->toIso8601String(),
            ];
        }

        return [
            'branches' => $branches,
        ];
    }

    public function optimize(User $user, int $groupId, int $listId): array
    {
        $comparison = $this->compare($user, $groupId, $listId);
        $branches = $comparison['branches'];
        $complete = array_values(array_filter($branches, function ($branch) {
            return $branch['missing_count'] === 0;
        }));
        usort($complete, function ($left, $right) {
            return $left['total'] <=> $right['total'];
        });

        $combined = $this->combined($branches);
        $cheapestComplete = count($complete) ? $complete[0] : null;

        return [
            'cheapest_complete' => $cheapestComplete,
            'combined' => $combined,
            'estimated_savings' => $cheapestComplete && $combined['total'] !== null
                ? round($cheapestComplete['total'] - $combined['total'], 2)
                : null,
        ];
    }

    private function combined(array $branches): array
    {
        $bestByItem = [];
        foreach ($branches as $branch) {
            foreach ($branch['items'] as $item) {
                $itemId = $item['item_id'];
                if (!isset($bestByItem[$itemId]) || $item['total'] < $bestByItem[$itemId]['total']) {
                    $bestByItem[$itemId] = [
                        'item_id' => $itemId,
                        'branch' => $branch['branch'],
                        'product' => $item['product'],
                        'total' => $item['total'],
                        'currency' => $item['currency'],
                    ];
                }
            }
        }

        $items = array_values($bestByItem);
        $currency = null;
        $total = 0.0;
        foreach ($items as $item) {
            if ($currency !== null && $currency !== $item['currency']) {
                return ['total' => null, 'currency' => null, 'items' => $items];
            }
            $currency = $currency ?: $item['currency'];
            $total += (float) $item['total'];
        }

        return [
            'total' => round($total, 2),
            'currency' => $currency,
            'items' => $items,
        ];
    }

    private function applyPromotion(float $lineTotal, $promotion): float
    {
        if (!$promotion || $promotion->status !== 'active') {
            return $lineTotal;
        }

        $now = now();
        if (($promotion->valid_from && $promotion->valid_from->gt($now)) || ($promotion->valid_to && $promotion->valid_to->lt($now))) {
            return $lineTotal;
        }

        if ($promotion->discount_type === 'percent') {
            return max(0, $lineTotal * (1 - ((float) $promotion->discount_value / 100)));
        }

        if ($promotion->discount_type === 'fixed') {
            return max(0, $lineTotal - (float) $promotion->discount_value);
        }

        return $lineTotal;
    }

    private function missingItem($item): array
    {
        return [
            'item_id' => $item->id,
            'product_id' => $item->product_id,
        ];
    }

    private function assertMember(User $user, int $groupId): void
    {
        $this->groups->findOrFailForUser($groupId, $user->id);
    }

    private function findList(int $groupId, int $listId): ShoppingList
    {
        $list = $this->lists->findInGroup($groupId, $listId);
        if (!$list) {
            throw new FamilyGroupException('SHOPPING_LIST_NOT_FOUND', 'La lista de compras no existe.', 404);
        }

        return $list;
    }
}
