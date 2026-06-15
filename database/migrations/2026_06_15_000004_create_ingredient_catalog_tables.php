<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIngredientCatalogTables extends Migration
{
    public function up()
    {
        Schema::create('unit_measures', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->string('type', 50)->nullable();
            $table->string('symbol', 30)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('ingredient_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->integer('parent_id')->unsigned()->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'status']);
            $table->foreign('parent_id')->references('id')->on('ingredient_categories')->onDelete('set null');
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 180);
            $table->string('normalized_name', 180)->unique();
            $table->integer('category_id')->unsigned()->nullable();
            $table->integer('base_unit_id')->unsigned()->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_generic')->default(true);
            $table->boolean('is_preparation')->default(false);
            $table->boolean('is_supplement')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status']);
            $table->foreign('category_id')->references('id')->on('ingredient_categories')->onDelete('set null');
            $table->foreign('base_unit_id')->references('id')->on('unit_measures')->onDelete('set null');
        });

        Schema::create('nutrients', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 80)->unique();
            $table->string('name', 150);
            $table->integer('unit_id')->unsigned();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->foreign('unit_id')->references('id')->on('unit_measures')->onDelete('restrict');
        });

        Schema::create('ingredient_nutrients', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('ingredient_id')->unsigned();
            $table->integer('nutrient_id')->unsigned();
            $table->decimal('amount_per_100g', 12, 4)->nullable();
            $table->string('source')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['ingredient_id', 'nutrient_id']);
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
            $table->foreign('nutrient_id')->references('id')->on('nutrients')->onDelete('cascade');
        });

        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('from_unit_id')->unsigned();
            $table->integer('to_unit_id')->unsigned();
            $table->integer('ingredient_id')->unsigned()->nullable();
            $table->decimal('factor', 16, 8);
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['from_unit_id', 'to_unit_id', 'ingredient_id']);
            $table->foreign('from_unit_id')->references('id')->on('unit_measures')->onDelete('cascade');
            $table->foreign('to_unit_id')->references('id')->on('unit_measures')->onDelete('cascade');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
        });

        Schema::create('ingredient_equivalences', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('source_ingredient_id')->unsigned();
            $table->integer('target_ingredient_id')->unsigned();
            $table->string('equivalence_type', 60)->nullable();
            $table->decimal('conversion_factor', 12, 4)->default(1);
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['source_ingredient_id', 'target_ingredient_id', 'equivalence_type']);
            $table->foreign('source_ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
            $table->foreign('target_ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
        });

        Schema::create('food_tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('type', 60)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('ingredient_tags', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('ingredient_id')->unsigned();
            $table->integer('food_tag_id')->unsigned();
            $table->timestamp('created_at')->nullable();

            $table->unique(['ingredient_id', 'food_tag_id']);
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
            $table->foreign('food_tag_id')->references('id')->on('food_tags')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ingredient_tags');
        Schema::dropIfExists('food_tags');
        Schema::dropIfExists('ingredient_equivalences');
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('ingredient_nutrients');
        Schema::dropIfExists('nutrients');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('ingredient_categories');
        Schema::dropIfExists('unit_measures');
    }
}
