<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserProfileTables extends Migration
{
    public function up()
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->date('birth_date')->nullable();
            $table->string('gender', 40)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->decimal('current_weight_kg', 6, 2)->nullable();
            $table->decimal('target_weight_kg', 6, 2)->nullable();
            $table->string('activity_level', 50)->nullable();
            $table->unsignedSmallInteger('meals_per_day')->nullable();
            $table->boolean('uses_app_for_health')->default(false);
            $table->boolean('uses_app_for_budget')->default(false);
            $table->boolean('uses_app_for_organization')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('body_measurements', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('waist_cm', 6, 2)->nullable();
            $table->unsignedSmallInteger('blood_pressure_systolic')->nullable();
            $table->unsignedSmallInteger('blood_pressure_diastolic')->nullable();
            $table->decimal('glucose_level', 6, 2)->nullable();
            $table->date('measurement_date');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'measurement_date']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('objectives', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('category', 80)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_objectives', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('objective_id')->unsigned();
            $table->unsignedSmallInteger('priority')->nullable();
            $table->decimal('target_value', 10, 2)->nullable();
            $table->string('target_unit', 40)->nullable();
            $table->date('target_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('objective_id')->references('id')->on('objectives')->onDelete('cascade');
        });

        Schema::create('dietary_restrictions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
        });

        Schema::create('user_dietary_restrictions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('dietary_restriction_id')->unsigned();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'dietary_restriction_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('dietary_restriction_id')->references('id')->on('dietary_restrictions')->onDelete('cascade');
        });

        Schema::create('health_conditions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
        });

        Schema::create('user_health_conditions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('health_condition_id')->unsigned();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'health_condition_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('health_condition_id')->references('id')->on('health_conditions')->onDelete('cascade');
        });

        Schema::create('allergies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
        });

        Schema::create('user_allergies', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('allergy_id')->unsigned();
            $table->string('severity', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'allergy_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('allergy_id')->references('id')->on('allergies')->onDelete('cascade');
        });

        Schema::create('user_nutrition_targets', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->unsignedInteger('daily_calories')->nullable();
            $table->decimal('daily_protein_g', 8, 2)->nullable();
            $table->decimal('daily_carbs_g', 8, 2)->nullable();
            $table->decimal('daily_fat_g', 8, 2)->nullable();
            $table->unsignedInteger('daily_sodium_mg')->nullable();
            $table->decimal('daily_sugar_g', 8, 2)->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('user_priority_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->decimal('health_weight', 5, 2)->default(0);
            $table->decimal('budget_weight', 5, 2)->default(0);
            $table->decimal('time_weight', 5, 2)->default(0);
            $table->decimal('stock_usage_weight', 5, 2)->default(0);
            $table->string('preferred_mode', 50)->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('professional_user_links', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('professional_user_id')->unsigned();
            $table->boolean('can_view_profile')->default(false);
            $table->boolean('can_view_stock')->default(false);
            $table->boolean('can_view_meal_plans')->default(false);
            $table->boolean('can_edit_meal_plans')->default(false);
            $table->boolean('can_view_reports')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'professional_user_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('professional_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('professional_user_links');
        Schema::dropIfExists('user_priority_settings');
        Schema::dropIfExists('user_nutrition_targets');
        Schema::dropIfExists('user_allergies');
        Schema::dropIfExists('allergies');
        Schema::dropIfExists('user_health_conditions');
        Schema::dropIfExists('health_conditions');
        Schema::dropIfExists('user_dietary_restrictions');
        Schema::dropIfExists('dietary_restrictions');
        Schema::dropIfExists('user_objectives');
        Schema::dropIfExists('objectives');
        Schema::dropIfExists('body_measurements');
        Schema::dropIfExists('user_profiles');
    }
}
