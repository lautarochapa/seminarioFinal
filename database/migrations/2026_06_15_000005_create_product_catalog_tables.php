<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateProductCatalogTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 150);
                $table->string('normalized_name', 150)->unique();
                $table->string('status', 30)->default('active');
                $table->timestamps();
                $table->softDeletes();

                $table->string('nombre')->nullable();
                $table->integer('padre')->nullable();
            });
        } else {
            Schema::table('brands', function (Blueprint $table) {
                if (! Schema::hasColumn('brands', 'name')) {
                    $table->string('name', 150)->nullable();
                }
                if (! Schema::hasColumn('brands', 'normalized_name')) {
                    $table->string('normalized_name', 150)->nullable();
                }
                if (! Schema::hasColumn('brands', 'status')) {
                    $table->string('status', 30)->default('active');
                }
                if (! Schema::hasColumn('brands', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        DB::statement("UPDATE brands SET name = COALESCE(name, nombre) WHERE name IS NULL");
        DB::statement("UPDATE brands SET normalized_name = lower(regexp_replace(COALESCE(normalized_name, name, nombre, 'brand-' || id), '\s+', ' ', 'g')) WHERE normalized_name IS NULL");

        Schema::create('product_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'status']);
            $table->foreign('parent_id')->references('id')->on('product_categories')->onDelete('set null');
        });

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 200);
                $table->string('normalized_name', 200)->unique();
                $table->unsignedBigInteger('brand_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->integer('ingredient_id')->unsigned()->nullable();
                $table->integer('default_unit_id')->unsigned()->nullable();
                $table->decimal('net_quantity', 12, 4)->nullable();
                $table->integer('package_unit_id')->unsigned()->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->boolean('is_active')->default(true);
                $table->string('status', 30)->default('active');
                $table->timestamps();
                $table->softDeletes();

                $table->string('nombre')->nullable();
                $table->string('codigo')->nullable();
                $table->string('img')->nullable();
                $table->integer('habilitado')->nullable();
                $table->integer('supply_id')->nullable();

                $table->index(['brand_id', 'status']);
                $table->index(['category_id', 'status']);
                $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
                $table->foreign('category_id')->references('id')->on('product_categories')->onDelete('set null');
                $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('set null');
                $table->foreign('default_unit_id')->references('id')->on('unit_measures')->onDelete('set null');
                $table->foreign('package_unit_id')->references('id')->on('unit_measures')->onDelete('set null');
            });
        } else {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'name')) {
                    $table->string('name', 200)->nullable();
                }
                if (! Schema::hasColumn('products', 'normalized_name')) {
                    $table->string('normalized_name', 200)->nullable();
                }
                if (! Schema::hasColumn('products', 'category_id')) {
                    $table->unsignedBigInteger('category_id')->nullable();
                }
                if (! Schema::hasColumn('products', 'ingredient_id')) {
                    $table->integer('ingredient_id')->unsigned()->nullable();
                }
                if (! Schema::hasColumn('products', 'default_unit_id')) {
                    $table->integer('default_unit_id')->unsigned()->nullable();
                }
                if (! Schema::hasColumn('products', 'net_quantity')) {
                    $table->decimal('net_quantity', 12, 4)->nullable();
                }
                if (! Schema::hasColumn('products', 'package_unit_id')) {
                    $table->integer('package_unit_id')->unsigned()->nullable();
                }
                if (! Schema::hasColumn('products', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('products', 'is_verified')) {
                    $table->boolean('is_verified')->default(false);
                }
                if (! Schema::hasColumn('products', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('products', 'status')) {
                    $table->string('status', 30)->default('active');
                }
                if (! Schema::hasColumn('products', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        DB::statement("UPDATE products SET name = COALESCE(name, nombre) WHERE name IS NULL");
        DB::statement("UPDATE products SET normalized_name = lower(regexp_replace(COALESCE(normalized_name, name, nombre, 'product-' || id), '\s+', ' ', 'g')) WHERE normalized_name IS NULL");

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->string('barcode', 80);
            $table->string('type', 50)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['barcode', 'type']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->string('image_url');
            $table->string('source', 80)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['product_id', 'is_primary']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        Schema::create('product_nutrients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->integer('nutrient_id')->unsigned();
            $table->decimal('amount_per_100g', 12, 4)->nullable();
            $table->decimal('amount_per_serving', 12, 4)->nullable();
            $table->decimal('serving_size', 12, 4)->nullable();
            $table->string('source')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['product_id', 'nutrient_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('nutrient_id')->references('id')->on('nutrients')->onDelete('cascade');
        });

        Schema::create('product_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->integer('food_tag_id')->unsigned();
            $table->timestamp('created_at')->nullable();

            $table->unique(['product_id', 'food_tag_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('food_tag_id')->references('id')->on('food_tags')->onDelete('cascade');
        });

        Schema::create('product_aliases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->string('alias', 200);
            $table->string('normalized_alias', 200);
            $table->string('source', 80)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('normalized_alias');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        Schema::create('product_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unsigned();
            $table->unsignedBigInteger('product_id');
            $table->string('report_type', 80);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('open');
            $table->integer('resolved_by')->unsigned()->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_reports');
        Schema::dropIfExists('product_aliases');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_nutrients');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('product_categories');
    }
}
