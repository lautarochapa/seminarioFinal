<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockTables extends Migration
{
    public function up()
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->string('name', 120);
            $table->string('type', 60)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['family_group_id', 'name']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
        });

        Schema::create('stock_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('stock_location_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->integer('unit_id')->unsigned();
            $table->date('purchase_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->boolean('is_open')->default(false);
            $table->decimal('estimated_purchase_price', 12, 2)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['family_group_id', 'status']);
            $table->index(['expiration_date', 'status']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('stock_location_id')->references('id')->on('stock_locations')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('unit_measures')->onDelete('restrict');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->unsignedBigInteger('stock_item_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('movement_type', 60);
            $table->decimal('quantity', 12, 4);
            $table->integer('unit_id')->unsigned();
            $table->text('reason')->nullable();
            $table->integer('related_recipe_id')->nullable();
            $table->integer('related_purchase_id')->nullable();
            $table->integer('related_meal_plan_item_id')->nullable();
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['family_group_id', 'movement_type']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('stock_item_id')->references('id')->on('stock_items')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('unit_measures')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('stock_minimum_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('ingredient_id')->unsigned()->nullable();
            $table->decimal('minimum_quantity', 12, 4);
            $table->integer('unit_id')->unsigned();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['family_group_id', 'status']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('unit_measures')->onDelete('restrict');
        });

        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->unsignedBigInteger('stock_item_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('alert_type', 60);
            $table->text('message');
            $table->string('severity', 30)->default('medium');
            $table->string('status', 30)->default('open');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['family_group_id', 'status']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('stock_item_id')->references('id')->on('stock_items')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });

        Schema::create('stock_waste_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('stock_item_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->integer('unit_id')->unsigned();
            $table->decimal('estimated_loss_amount', 12, 2)->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['family_group_id', 'processed_at']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('stock_item_id')->references('id')->on('stock_items')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('unit_measures')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_waste_logs');
        Schema::dropIfExists('stock_alerts');
        Schema::dropIfExists('stock_minimum_rules');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('stock_locations');
    }
}
