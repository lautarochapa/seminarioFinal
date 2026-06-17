<?php

namespace App\Services\AdminReports;

use App\ImportedRecipeCandidate;
use App\RecipeCookLog;
use Illuminate\Support\Facades\DB;

class AdminReportService
{
    public function usersActive(array $filters): array
    {
        $query = DB::table('login_logs')->where('success', true);

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $totals = (clone $query)
            ->selectRaw('COUNT(*) as total_logins, COUNT(DISTINCT user_id) as unique_users')
            ->first();

        $byDay = (clone $query)
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') as day, COUNT(*) as logins, COUNT(DISTINCT user_id) as unique_users")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return [
            'total_logins'       => (int) $totals->total_logins,
            'unique_users'       => (int) $totals->unique_users,
            'by_day'             => $byDay->toArray(),
        ];
    }

    public function productsPendingReview(array $filters): array
    {
        $query = DB::table('scraped_product_candidates as c')
            ->leftJoin('scraping_sources as s', 's.id', '=', 'c.source_id')
            ->where('c.review_status', 'pending');

        if (!empty($filters['date_from'])) {
            $query->whereDate('c.created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('c.created_at', '<=', $filters['date_to']);
        }

        $total = (clone $query)->count();

        $bySource = (clone $query)
            ->select('s.name as source_name', 's.code as source_code', DB::raw('COUNT(*) as count'))
            ->groupBy('s.name', 's.code')
            ->orderByDesc('count')
            ->get();

        $oldest = (clone $query)
            ->select('c.id', 'c.raw_name', 'c.raw_brand', 'c.raw_price', 'c.created_at')
            ->orderBy('c.created_at')
            ->limit(10)
            ->get();

        return [
            'total_pending'  => $total,
            'by_source'      => $bySource->toArray(),
            'oldest_pending' => $oldest->toArray(),
        ];
    }

    public function recipesPendingReview(array $filters): array
    {
        $query = ImportedRecipeCandidate::where('status', 'pending');

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $total = (clone $query)->count();

        $bySite = (clone $query)
            ->select('source_site', DB::raw('COUNT(*) as count'))
            ->groupBy('source_site')
            ->orderByDesc('count')
            ->get(['source_site', DB::raw('COUNT(*) as count')]);

        $oldest = (clone $query)
            ->orderBy('created_at')
            ->limit(10)
            ->get(['id', 'source_site', 'raw_title', 'created_at']);

        return [
            'total_pending'  => $total,
            'by_site'        => $bySite->toArray(),
            'oldest_pending' => $oldest->toArray(),
        ];
    }

    public function priceVariations(array $filters): array
    {
        $dateFilter = '';
        $bindings   = [];

        if (!empty($filters['date_from'])) {
            $dateFilter .= " AND spp.scraped_at::date >= ?";
            $bindings[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $dateFilter .= " AND spp.scraped_at::date <= ?";
            $bindings[] = $filters['date_to'];
        }

        $limit = min(100, max(1, (int) ($filters['limit'] ?? 50)));

        $sql = "
            WITH ranked AS (
                SELECT
                    spp.supermarket_product_id,
                    spp.price,
                    spp.currency,
                    spp.scraped_at,
                    ROW_NUMBER() OVER (PARTITION BY spp.supermarket_product_id ORDER BY spp.scraped_at DESC) AS rn
                FROM supermarket_product_prices spp
                WHERE 1=1 {$dateFilter}
            )
            SELECT
                r1.supermarket_product_id,
                p.name AS product_name,
                sc.name AS chain_name,
                r1.price AS current_price,
                r2.price AS previous_price,
                ROUND((r1.price - r2.price)::numeric, 2) AS variation,
                ROUND(((r1.price - r2.price) / NULLIF(r2.price, 0) * 100)::numeric, 2) AS variation_percent,
                r1.currency,
                r1.scraped_at AS last_scraped_at
            FROM ranked r1
            JOIN ranked r2 ON r2.supermarket_product_id = r1.supermarket_product_id AND r2.rn = 2
            JOIN supermarket_products sp ON sp.id = r1.supermarket_product_id
            JOIN products p ON p.id = sp.product_id
            JOIN supermarket_chains sc ON sc.id = sp.supermarket_chain_id
            WHERE r1.rn = 1
              AND r1.price != r2.price
            ORDER BY ABS(r1.price - r2.price) DESC
            LIMIT {$limit}
        ";

        $rows = DB::select($sql, $bindings);

        return [
            'total'      => count($rows),
            'limit'      => $limit,
            'variations' => $rows,
        ];
    }

    public function mostUsedRecipes(array $filters): array
    {
        $query = DB::table('recipe_cook_logs as rcl')
            ->join('recipes as r', 'r.id', '=', 'rcl.recipe_id');

        if (!empty($filters['date_from'])) {
            $query->whereDate('rcl.cooked_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('rcl.cooked_at', '<=', $filters['date_to']);
        }

        $limit = min(100, max(1, (int) ($filters['limit'] ?? 20)));

        $totals = (clone $query)
            ->selectRaw('COUNT(*) as total_cook_events, COALESCE(SUM(rcl.servings), 0) as total_servings')
            ->first();

        $top = (clone $query)
            ->select('rcl.recipe_id', 'r.nombre as recipe_name', DB::raw('COUNT(*) as times_cooked'), DB::raw('COALESCE(SUM(rcl.servings), 0) as total_servings'))
            ->groupBy('rcl.recipe_id', 'r.nombre')
            ->orderByDesc('times_cooked')
            ->limit($limit)
            ->get();

        return [
            'total_cook_events' => (int) $totals->total_cook_events,
            'total_servings'    => (int) $totals->total_servings,
            'top_recipes'       => $top->toArray(),
        ];
    }

    public function supermarketPriceStatus(array $filters): array
    {
        $now = now()->toDateTimeString();

        $byChain = DB::table('supermarket_products as sp')
            ->join('supermarket_chains as sc', 'sc.id', '=', 'sp.supermarket_chain_id')
            ->leftJoin('supermarket_product_prices as spp', function ($join) {
                $join->on('spp.supermarket_product_id', '=', 'sp.id');
            })
            ->select(
                'sc.id as chain_id',
                'sc.name as chain_name',
                DB::raw('COUNT(DISTINCT sp.id) as total_products'),
                DB::raw("COUNT(DISTINCT CASE WHEN spp.id IS NOT NULL AND (spp.valid_to IS NULL OR spp.valid_to >= NOW()) THEN sp.id END) as with_valid_price"),
                DB::raw("COUNT(DISTINCT CASE WHEN spp.id IS NOT NULL AND spp.valid_to IS NOT NULL AND spp.valid_to < NOW() THEN sp.id END) as with_expired_price"),
                DB::raw("COUNT(DISTINCT CASE WHEN spp.id IS NULL THEN sp.id END) as without_price"),
                DB::raw('MAX(spp.scraped_at) as last_scraped_at')
            )
            ->where('sp.status', 'active')
            ->when(!empty($filters['chain_id']), function ($q) use ($filters) {
                $q->where('sc.id', (int) $filters['chain_id']);
            })
            ->groupBy('sc.id', 'sc.name')
            ->orderBy('sc.name')
            ->get();

        return [
            'total_chains' => $byChain->count(),
            'chains'       => $byChain->toArray(),
        ];
    }
}
