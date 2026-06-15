<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMealPlanTables extends Migration
{
    public function up()
    {
        Schema::create('meal_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('meal_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->integer('created_by')->unsigned()->nullable();
            $table->string('period_type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 30)->default('draft');
            $table->string('mode', 50)->nullable();
            $table->jsonb('config_json')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['family_group_id', 'start_date', 'end_date']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('meal_plan_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('meal_plan_id');
            $table->date('date');
            $table->integer('meal_type_id')->unsigned();
            $table->unsignedBigInteger('recipe_id')->nullable();
            $table->string('free_meal_description')->nullable();
            $table->boolean('is_eating_out')->default(false);
            $table->decimal('servings_total', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('planned');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['meal_plan_id', 'date']);
            $table->foreign('meal_plan_id')->references('id')->on('meal_plans')->onDelete('cascade');
            $table->foreign('meal_type_id')->references('id')->on('meal_types')->onDelete('restrict');
            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('set null');
        });

        Schema::create('meal_plan_item_portions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('meal_plan_item_id');
            $table->integer('user_id')->unsigned();
            $table->decimal('portion_factor', 8, 4)->default(1);
            $table->decimal('servings', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['meal_plan_item_id', 'user_id']);
            $table->foreign('meal_plan_item_id')->references('id')->on('meal_plan_items')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('meal_plan_preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->integer('user_id')->unsigned()->nullable();
            $table->boolean('avoid_repetition')->default(true);
            $table->boolean('respect_budget')->default(true);
            $table->boolean('respect_nutrition')->default(true);
            $table->boolean('respect_stock')->default(true);
            $table->string('preferred_mode', 50)->nullable();
            $table->jsonb('config_json')->nullable();
            $table->timestamps();

            $table->unique(['family_group_id', 'user_id']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('meal_plan_suggestions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned();
            $table->unsignedBigInteger('meal_plan_id')->nullable();
            $table->unsignedBigInteger('recipe_id')->unsigned();
            $table->text('suggestion_reason')->nullable();
            $table->decimal('score', 8, 4)->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('created_at')->nullable();

            $table->index(['family_group_id', 'status']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('meal_plan_id')->references('id')->on('meal_plans')->onDelete('cascade');
            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
        });

        Schema::create('meal_plan_incompatibilities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('meal_plan_id');
            $table->unsignedBigInteger('meal_plan_item_id')->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('incompatibility_type', 80);
            $table->text('message');
            $table->string('severity', 30)->default('medium');
            $table->string('status', 30)->default('open');
            $table->timestamp('created_at')->nullable();

            $table->index(['meal_plan_id', 'status']);
            $table->foreign('meal_plan_id')->references('id')->on('meal_plans')->onDelete('cascade');
            $table->foreign('meal_plan_item_id')->references('id')->on('meal_plan_items')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('meal_consumption_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('meal_plan_item_id');
            $table->integer('user_id')->unsigned();
            $table->boolean('consumed')->default(true);
            $table->decimal('portion_factor', 8, 4)->default(1);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['meal_plan_item_id', 'user_id']);
            $table->foreign('meal_plan_item_id')->references('id')->on('meal_plan_items')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('meal_consumption_logs');
        Schema::dropIfExists('meal_plan_incompatibilities');
        Schema::dropIfExists('meal_plan_suggestions');
        Schema::dropIfExists('meal_plan_preferences');
        Schema::dropIfExists('meal_plan_item_portions');
        Schema::dropIfExists('meal_plan_items');
        Schema::dropIfExists('meal_plans');
        Schema::dropIfExists('meal_types');
    }
}
