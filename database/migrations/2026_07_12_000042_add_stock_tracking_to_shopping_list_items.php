<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockTrackingToShoppingListItems extends Migration
{
    public function up()
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            if (! Schema::hasColumn('shopping_list_items', 'stock_processed_at')) {
                $table->timestamp('stock_processed_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('shopping_list_items', 'purchase_item_id')) {
                $table->unsignedBigInteger('purchase_item_id')->nullable()->after('stock_processed_at');
                $table->foreign('purchase_item_id')->references('id')->on('purchase_items')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            if (Schema::hasColumn('shopping_list_items', 'purchase_item_id')) {
                $table->dropForeign(['purchase_item_id']);
                $table->dropColumn('purchase_item_id');
            }
            if (Schema::hasColumn('shopping_list_items', 'stock_processed_at')) {
                $table->dropColumn('stock_processed_at');
            }
        });
    }
}
