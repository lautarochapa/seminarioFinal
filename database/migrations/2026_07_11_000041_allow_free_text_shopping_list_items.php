<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AllowFreeTextShoppingListItems extends Migration
{
    public function up()
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            if (! Schema::hasColumn('shopping_list_items', 'free_text_name')) {
                $table->string('free_text_name', 180)->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('shopping_list_items', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('status');
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE shopping_list_items ALTER COLUMN quantity DROP NOT NULL');
            DB::statement('ALTER TABLE shopping_list_items ALTER COLUMN unit_id DROP NOT NULL');
        }
    }

    public function down()
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('UPDATE shopping_list_items SET quantity = 1 WHERE quantity IS NULL');
            DB::statement('ALTER TABLE shopping_list_items ALTER COLUMN quantity SET NOT NULL');
        }

        Schema::table('shopping_list_items', function (Blueprint $table) {
            if (Schema::hasColumn('shopping_list_items', 'free_text_name')) {
                $table->dropColumn('free_text_name');
            }
            if (Schema::hasColumn('shopping_list_items', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
}
