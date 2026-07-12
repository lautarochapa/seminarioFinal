<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateRecipeTables extends Migration
{
    public function up()
    {
        Schema::create('recipe_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'status']);
            $table->foreign('parent_id')->references('id')->on('recipe_categories')->onDelete('set null');
        });

        if (! Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->bigIncrements('id');
            });
        }

        Schema::table('recipes', function (Blueprint $table) {
            foreach ([
                'name' => ['string', 200],
                'normalized_name' => ['string', 200],
                'source_type' => ['string', 60],
                'difficulty' => ['string', 50],
                'source_url' => ['string', 255],
                'source_site' => ['string', 120],
                'source_author' => ['string', 150],
                'status' => ['string', 30],
            ] as $column => $definition) {
                if (! Schema::hasColumn('recipes', $column)) {
                    $table->{$definition[0]}($column, $definition[1])->nullable();
                }
            }

            if (! Schema::hasColumn('recipes', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'owner_user_id')) {
                $table->integer('owner_user_id')->unsigned()->nullable();
            }
            if (! Schema::hasColumn('recipes', 'is_public')) {
                $table->boolean('is_public')->default(false);
            }
            if (! Schema::hasColumn('recipes', 'is_official')) {
                $table->boolean('is_official')->default(false);
            }
            if (! Schema::hasColumn('recipes', 'is_verified')) {
                $table->boolean('is_verified')->default(false);
            }
            if (! Schema::hasColumn('recipes', 'servings')) {
                $table->unsignedSmallInteger('servings')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'prep_time_minutes')) {
                $table->unsignedSmallInteger('prep_time_minutes')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'cook_time_minutes')) {
                $table->unsignedSmallInteger('cook_time_minutes')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'branched_from_recipe_id')) {
                $table->unsignedBigInteger('branched_from_recipe_id')->nullable();
            }
            if (! Schema::hasColumn('recipes', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('recipes', 'nombre')) {
            DB::statement("UPDATE recipes SET name = COALESCE(name, nombre) WHERE name IS NULL");
        }

        if (Schema::hasColumn('recipes', 'descripcion')) {
            DB::statement("UPDATE recipes SET description = COALESCE(description, descripcion) WHERE description IS NULL");
        }

        if (Schema::hasColumn('recipes', 'nombre')) {
            DB::statement("UPDATE recipes SET normalized_name = lower(regexp_replace(COALESCE(normalized_name, name, nombre, 'recipe-' || id), '\s+', ' ', 'g')) WHERE normalized_name IS NULL");
        } else {
            DB::statement("UPDATE recipes SET normalized_name = lower(regexp_replace(COALESCE(normalized_name, name, 'recipe-' || id), '\s+', ' ', 'g')) WHERE normalized_name IS NULL");
        }
        DB::statement("UPDATE recipes SET status = COALESCE(status, 'active') WHERE status IS NULL");

        Schema::table('recipes', function (Blueprint $table) {
            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('recipe_categories')->onDelete('set null');
            $table->foreign('branched_from_recipe_id')->references('id')->on('recipes')->onDelete('set null');
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            $table->integer('ingredient_id')->unsigned();
            $table->unsignedBigInteger('specific_product_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->integer('unit_id')->unsigned();
            $table->boolean('is_optional')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('restrict');
            $table->foreign('specific_product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('unit_measures')->onDelete('restrict');
        });

        Schema::create('recipe_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedSmallInteger('step_number');
            $table->text('description');
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->timestamps();

            $table->unique(['recipe_id', 'step_number']);
            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
        });

        Schema::create('recipe_images', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            $table->string('image_url');
            $table->string('source', 80)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
        });

        Schema::create('recipe_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 80)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('type', 60)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('recipe_tag_pivot', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedBigInteger('recipe_tag_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['recipe_id', 'recipe_tag_id']);
            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('recipe_tag_id')->references('id')->on('recipe_tags')->onDelete('cascade');
        });

        Schema::create('recipe_nutrition', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            foreach (['calories', 'protein', 'carbohydrates', 'fat', 'sodium', 'sugar', 'fiber'] as $nutrient) {
                $table->decimal($nutrient.'_total', 12, 4)->nullable();
                $table->decimal($nutrient.'_per_serving', 12, 4)->nullable();
            }
            $table->string('calculation_status', 30)->default('pending');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique('recipe_id');
            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
        });

        Schema::create('recipe_cost_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            $table->integer('family_group_id')->unsigned()->nullable();
            $table->integer('supermarket_chain_id')->unsigned()->nullable();
            $table->integer('supermarket_branch_id')->unsigned()->nullable();
            $table->decimal('estimated_total_cost', 12, 2)->nullable();
            $table->decimal('estimated_cost_per_serving', 12, 2)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('set null');
            $table->foreign('supermarket_chain_id')->references('id')->on('supermarket_chains')->onDelete('set null');
            $table->foreign('supermarket_branch_id')->references('id')->on('supermarket_branches')->onDelete('set null');
        });

        Schema::create('recipe_sources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            $table->string('source_url');
            $table->string('source_site', 120)->nullable();
            $table->string('source_author', 150)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
        });

        Schema::create('recipe_branches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('original_recipe_id');
            $table->unsignedBigInteger('branched_recipe_id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['original_recipe_id', 'branched_recipe_id']);
            $table->foreign('original_recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('branched_recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('recipe_favorites', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unsigned();
            $table->unsignedBigInteger('recipe_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'recipe_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
        });

        Schema::create('recipe_cook_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('family_group_id')->unsigned()->nullable();
            $table->unsignedBigInteger('recipe_id');
            $table->integer('meal_plan_item_id')->nullable();
            $table->unsignedSmallInteger('servings')->nullable();
            $table->timestamp('cooked_at')->nullable();
            $table->boolean('stock_discounted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('set null');
            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
        });

        Schema::create('recipe_substitutions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            $table->integer('source_ingredient_id')->unsigned();
            $table->integer('target_ingredient_id')->unsigned();
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('source_ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
            $table->foreign('target_ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
        });

        Schema::create('imported_recipe_candidates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('source_url')->nullable();
            $table->string('source_site', 120)->nullable();
            $table->string('raw_title')->nullable();
            $table->text('raw_description')->nullable();
            $table->jsonb('raw_ingredients_json')->nullable();
            $table->jsonb('raw_steps_json')->nullable();
            $table->string('raw_image_url')->nullable();
            $table->jsonb('parsed_recipe_json')->nullable();
            $table->string('status', 30)->default('pending');
            $table->integer('reviewed_by')->unsigned()->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('created_recipe_id')->nullable();
            $table->timestamps();

            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_recipe_id')->references('id')->on('recipes')->onDelete('set null');
        });

        Schema::create('recipe_review_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id')->nullable();
            $table->unsignedBigInteger('imported_recipe_candidate_id')->nullable();
            $table->integer('reviewed_by')->unsigned()->nullable();
            $table->string('action', 80);
            $table->text('comments')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('imported_recipe_candidate_id')->references('id')->on('imported_recipe_candidates')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('recipe_review_logs');
        Schema::dropIfExists('imported_recipe_candidates');
        Schema::dropIfExists('recipe_substitutions');
        Schema::dropIfExists('recipe_cook_logs');
        Schema::dropIfExists('recipe_favorites');
        Schema::dropIfExists('recipe_branches');
        Schema::dropIfExists('recipe_sources');
        Schema::dropIfExists('recipe_cost_snapshots');
        Schema::dropIfExists('recipe_nutrition');
        Schema::dropIfExists('recipe_tag_pivot');
        Schema::dropIfExists('recipe_tags');
        Schema::dropIfExists('recipe_images');
        Schema::dropIfExists('recipe_steps');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipe_categories');
    }
}
