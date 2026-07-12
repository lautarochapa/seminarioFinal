<?php

namespace App\Repositories\WasteReports;

use App\StockMovement;

class WasteReportRepository
{
    public function paginate(int $groupId, array $filters)
    {
        $query = $this->baseQuery($groupId, $filters);
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }

    public function totals(int $groupId, array $filters): array
    {
        $items = $this->baseQuery($groupId, $filters)->get();
        $totalQuantity = 0.0;
        $estimatedLoss = 0.0;
        $itemsWithPrice = 0;

        foreach ($items as $movement) {
            $quantity = abs((float) $movement->quantity);
            $totalQuantity += $quantity;

            if ($movement->stockItem && $movement->stockItem->estimated_purchase_price !== null) {
                $estimatedLoss += $quantity * (float) $movement->stockItem->estimated_purchase_price;
                $itemsWithPrice++;
            }
        }

        return [
            'discarded_quantity' => round($totalQuantity, 4),
            'estimated_loss' => round($estimatedLoss, 2),
            'items_with_price' => $itemsWithPrice,
            'items_without_price' => $items->count() - $itemsWithPrice,
        ];
    }

    private function baseQuery(int $groupId, array $filters)
    {
        $query = StockMovement::with(['product', 'unit', 'stockItem.location'])
            ->where('family_group_id', $groupId)
            ->whereIn('movement_type', ['discard', 'expiration']);

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['reason'])) {
            $query->where('reason', 'ILIKE', '%'.$filters['reason'].'%');
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['stock_location_id'])) {
            $query->whereHas('stockItem', function ($q) use ($filters) {
                $q->where('stock_location_id', (int) $filters['stock_location_id']);
            });
        }

        return $query;
    }
}
