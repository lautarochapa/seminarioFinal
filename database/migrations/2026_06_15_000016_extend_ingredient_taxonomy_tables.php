<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ExtendIngredientTaxonomyTables extends Migration
{
    public function up()
    {
        Schema::table('ingredient_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('ingredient_categories', 'code')) {
                $table->string('code', 120)->nullable()->after('id');
            }

            if (! Schema::hasColumn('ingredient_categories', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('description');
            }

            if (! Schema::hasColumn('ingredient_categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });

        DB::table('ingredient_categories')
            ->whereNull('code')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function ($category) {
                DB::table('ingredient_categories')
                    ->where('id', $category->id)
                    ->update([
                        'code' => Str::upper(Str::slug($category->name ?: 'category-'.$category->id, '_')).'_'.$category->id,
                        'is_active' => true,
                    ]);
            });

        Schema::table('ingredient_categories', function (Blueprint $table) {
            $table->unique('code');
            $table->index('sort_order');
            $table->index('is_active');
        });

        Schema::table('food_tags', function (Blueprint $table) {
            if (! Schema::hasColumn('food_tags', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('allergies', function (Blueprint $table) {
            if (! Schema::hasColumn('allergies', 'created_at')) {
                $table->timestamps();
            }

            if (! Schema::hasColumn('allergies', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down()
    {
        Schema::table('allergies', function (Blueprint $table) {
            if (Schema::hasColumn('allergies', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('allergies', 'created_at')) {
                $table->dropTimestamps();
            }
        });

        Schema::table('food_tags', function (Blueprint $table) {
            if (Schema::hasColumn('food_tags', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('ingredient_categories', function (Blueprint $table) {
            if (Schema::hasColumn('ingredient_categories', 'code')) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            }

            if (Schema::hasColumn('ingredient_categories', 'sort_order')) {
                $table->dropIndex(['sort_order']);
                $table->dropColumn('sort_order');
            }

            if (Schema::hasColumn('ingredient_categories', 'is_active')) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            }
        });
    }
}
