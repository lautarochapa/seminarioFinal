<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPriceMetadataToShoppingListItems extends Migration
{
    public function up()
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->string('price_source', 30)->nullable()->after('actual_price');
            $table->timestamp('price_updated_at')->nullable()->after('price_source');
            $table->unsignedBigInteger('supermarket_chain_id')->nullable()->after('price_updated_at');
            $table->unsignedBigInteger('supermarket_branch_id')->nullable()->after('supermarket_chain_id');
            $table->string('source_type', 30)->nullable()->after('supermarket_branch_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');

            $table->foreign('supermarket_chain_id')->references('id')->on('supermarket_chains')->onDelete('set null');
            $table->foreign('supermarket_branch_id')->references('id')->on('supermarket_branches')->onDelete('set null');
            $table->index('price_source');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down()
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropForeign(['supermarket_chain_id']);
            $table->dropForeign(['supermarket_branch_id']);
            $table->dropIndex(['price_source']);
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn([
                'price_source',
                'price_updated_at',
                'supermarket_chain_id',
                'supermarket_branch_id',
                'source_type',
                'source_id',
            ]);
        });
    }
}
