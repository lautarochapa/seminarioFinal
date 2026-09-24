<?php

namespace App\Http\Controllers;

use App\Http\Resources\Api\V1\HouseholdStock\StockItemResource;
use App\Http\Resources\Api\V1\StockLocations\StockLocationResource;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\HouseholdStock\HouseholdStockRepository;
use App\Repositories\StockLocations\StockLocationRepository;
use App\StockItem;
use Illuminate\Http\Request;

class WebStockOverviewController extends Controller
{
    public function __invoke(Request $request, $id, FamilyGroupRepository $groups,
        HouseholdStockRepository $stock, StockLocationRepository $locations)
    {
        $filters = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'stock_location_id' => 'sometimes|integer|min:1',
            'expires_before' => 'sometimes|date_format:Y-m-d',
        ]);
        $groups->findOrFailForUser((int) $id, $request->user()->id);
        $items = $stock->paginateForGroup((int) $id, $filters);
        // Aggregate in SQL instead of hydrating the whole household for four counters.
        $totals = StockItem::where('family_group_id', $id)->where('status', 'active')
            ->selectRaw('COUNT(*) AS total_items, COUNT(DISTINCT product_id) AS distinct_products')
            ->selectRaw('SUM(CASE WHEN expiration_date <= ? THEN 1 ELSE 0 END) AS expiring_soon', [now()->addDays(7)->toDateString()])
            ->selectRaw('COALESCE(SUM(quantity * estimated_purchase_price), 0) AS total_value, COUNT(estimated_purchase_price) AS valued_items')
            ->first();
        return response()->json([
            'data' => StockItemResource::collection($items),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(), 'total' => $items->total()],
            'locations' => StockLocationResource::collection($locations->activeOptionsForGroup((int) $id)),
            'summary' => ['total_items' => (int) $totals->total_items,
                'distinct_products' => (int) $totals->distinct_products, 'expiring_soon' => (int) $totals->expiring_soon],
            'value' => ['total_value' => round((float) $totals->total_value, 2),
                'valued_items' => (int) $totals->valued_items, 'currency' => 'ARS'],
            'trace_id' => $request->attributes->get('trace_id'),
        ])->header('Cache-Control', 'no-store, private');
    }
}
