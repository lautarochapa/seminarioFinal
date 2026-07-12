<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShoppingTables extends Migration
{
    public function up()
    {
        Schema::create('shopping_lists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->unsignedBigInteger('meal_plan_id')->nullable();
            $table->integer('created_by')->unsigned()->nullable();
            $table->string('source_type', 60)->default('manual');
            $table->string('status', 30)->default('draft');
            $table->decimal('estimated_total', 12, 2)->nullable();
            $table->integer('selected_supermarket_branch_id')->unsigned()->nullable();
            $table->string('optimization_mode', 60)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['family_group_id', 'status']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('meal_plan_id')->references('id')->on('meal_plans')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('selected_supermarket_branch_id')->references('id')->on('supermarket_branches')->onDelete('set null');
        });

        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shopping_list_id');
            $table->integer('ingredient_id')->unsigned()->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('selected_supermarket_product_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->integer('unit_id')->unsigned();
            $table->decimal('estimated_price', 12, 2)->nullable();
            $table->decimal('actual_price', 12, 2)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shopping_list_id', 'status']);
            $table->foreign('shopping_list_id')->references('id')->on('shopping_lists')->onDelete('cascade');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('selected_supermarket_product_id')->references('id')->on('supermarket_products')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('unit_measures')->onDelete('restrict');
        });

        Schema::create('shopping_list_item_alternatives', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shopping_list_item_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('supermarket_product_id')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->foreign('shopping_list_item_id')->references('id')->on('shopping_list_items')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('supermarket_product_id')->references('id')->on('supermarket_products')->onDelete('set null');
        });

        Schema::create('shopping_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shopping_list_id');
            $table->integer('family_group_id')->unsigned();
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('supermarket_branch_id')->unsigned()->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->foreign('shopping_list_id')->references('id')->on('shopping_lists')->onDelete('cascade');
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('supermarket_branch_id')->references('id')->on('supermarket_branches')->onDelete('set null');
        });

        Schema::create('shopping_session_scans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shopping_session_id');
            $table->string('barcode', 80)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('shopping_list_item_id')->nullable();
            $table->decimal('quantity', 12, 4)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('scan_result', 60)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('shopping_session_id')->references('id')->on('shopping_sessions')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('shopping_list_item_id')->references('id')->on('shopping_list_items')->onDelete('set null');
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->unsignedBigInteger('shopping_list_id')->nullable();
            $table->integer('supermarket_branch_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('payment_method_id')->unsigned()->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('estimated_total', 12, 2)->nullable();
            $table->decimal('actual_total', 12, 2)->nullable();
            $table->string('status', 30)->default('confirmed');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['family_group_id', 'purchase_date']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('shopping_list_id')->references('id')->on('shopping_lists')->onDelete('set null');
            $table->foreign('supermarket_branch_id')->references('id')->on('supermarket_branches')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 12, 4);
            $table->integer('unit_id')->unsigned();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->date('expiration_date')->nullable();
            $table->unsignedBigInteger('created_stock_item_id')->nullable();
            $table->timestamps();

            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('unit_measures')->onDelete('restrict');
            $table->foreign('created_stock_item_id')->references('id')->on('stock_items')->onDelete('set null');
        });

        Schema::create('product_preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->integer('user_id')->unsigned()->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('preference_type', 60);
            $table->unsignedSmallInteger('priority')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['family_group_id', 'preference_type']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_preferences');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('shopping_session_scans');
        Schema::dropIfExists('shopping_sessions');
        Schema::dropIfExists('shopping_list_item_alternatives');
        Schema::dropIfExists('shopping_list_items');
        Schema::dropIfExists('shopping_lists');
    }
}
