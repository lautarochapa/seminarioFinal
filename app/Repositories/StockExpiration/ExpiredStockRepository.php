<?php

namespace App\Repositories\StockExpiration;

use App\StockItem;
use App\StockWasteLog;

class ExpiredStockRepository
{
    public function pendingExpiredItems(int $limit)
    {
        return StockItem::with(['product'])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', now()->toDateString())
            ->where('quantity', '>', 0)
            ->whereNotIn('id', function ($query) {
                $query->select('stock_item_id')
                    ->from('stock_waste_logs')
                    ->whereNotNull('stock_item_id');
            })
            ->orderBy('expiration_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function lockStockItem(int $stockItemId): ?StockItem
    {
        return StockItem::where('id', $stockItemId)
            ->lockForUpdate()
            ->first();
    }

    public function alreadyProcessed(int $stockItemId): bool
    {
        return StockWasteLog::where('stock_item_id', $stockItemId)
            ->lockForUpdate()
            ->exists();
    }
}
