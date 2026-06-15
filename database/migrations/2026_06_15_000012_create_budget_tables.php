<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBudgetTables extends Migration
{
    public function up()
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('ARS');
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['family_group_id', 'year', 'month']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
        });

        Schema::create('budget_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('budget_id');
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->integer('ingredient_category_id')->unsigned()->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['budget_id', 'status']);
            $table->foreign('budget_id')->references('id')->on('budgets')->onDelete('cascade');
            $table->foreign('product_category_id')->references('id')->on('product_categories')->onDelete('cascade');
            $table->foreign('ingredient_category_id')->references('id')->on('ingredient_categories')->onDelete('cascade');
        });

        Schema::create('budget_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('budget_id');
            $table->string('movement_type', 60);
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('related_purchase_id')->nullable();
            $table->unsignedBigInteger('related_shopping_list_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['budget_id', 'movement_type']);
            $table->foreign('budget_id')->references('id')->on('budgets')->onDelete('cascade');
            $table->foreign('related_purchase_id')->references('id')->on('purchases')->onDelete('set null');
            $table->foreign('related_shopping_list_id')->references('id')->on('shopping_lists')->onDelete('set null');
        });

        Schema::create('budget_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('budget_id');
            $table->string('alert_type', 60);
            $table->text('message');
            $table->string('severity', 30)->default('info');
            $table->string('status', 30)->default('unread');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['budget_id', 'status']);
            $table->foreign('budget_id')->references('id')->on('budgets')->onDelete('cascade');
        });

        Schema::create('budget_projections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('budget_id');
            $table->decimal('actual_spent', 12, 2)->default(0);
            $table->decimal('planned_reserved', 12, 2)->default(0);
            $table->decimal('real_available', 12, 2)->default(0);
            $table->decimal('projected_available', 12, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('budget_id')->references('id')->on('budgets')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('budget_projections');
        Schema::dropIfExists('budget_alerts');
        Schema::dropIfExists('budget_movements');
        Schema::dropIfExists('budget_categories');
        Schema::dropIfExists('budgets');
    }
}
