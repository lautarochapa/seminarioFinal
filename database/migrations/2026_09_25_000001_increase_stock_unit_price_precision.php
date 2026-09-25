<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class IncreaseStockUnitPricePrecision extends Migration
{
    public function up()
    {
        // 14 integral digits cover a 12,2 package price / minimum 12,4 content;
        // 12 decimal places keep valuation rounding below a cent for a maximum 12,4 stock lot.
        DB::statement('ALTER TABLE stock_items ALTER COLUMN estimated_purchase_price TYPE NUMERIC(26,12)');
    }

    public function down()
    {
        // Refuse a rollback that would silently round away valid fractional unit prices.
        $wouldLoseValue = DB::table('stock_items')->whereNotNull('estimated_purchase_price')
            ->where(function ($query) {
                $query->whereRaw('estimated_purchase_price <> ROUND(estimated_purchase_price, 2)')
                    ->orWhereRaw('ABS(estimated_purchase_price) > 9999999999.99');
            })->exists();
        if ($wouldLoseValue) {
            throw new RuntimeException('Cannot reduce stock price precision while fractional unit prices or larger values exist. Preserve or reconcile those prices before rollback.');
        }
        DB::statement('ALTER TABLE stock_items ALTER COLUMN estimated_purchase_price TYPE NUMERIC(12,2)');
    }
}
