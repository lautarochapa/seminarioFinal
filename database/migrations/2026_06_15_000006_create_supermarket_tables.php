<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSupermarketTables extends Migration
{
    public function up()
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->string('province', 150)->nullable();
            $table->string('country', 100)->default('Argentina');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['name', 'province', 'country']);
        });

        Schema::create('supermarket_chains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->string('code', 80)->unique();
            $table->string('website_url')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supermarket_branches', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('supermarket_chain_id')->unsigned();
            $table->integer('city_id')->unsigned();
            $table->string('name', 150);
            $table->string('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 60)->nullable();
            $table->text('opening_hours')->nullable();
            $table->boolean('delivery_available')->default(false);
            $table->boolean('pickup_available')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supermarket_chain_id', 'city_id', 'status']);
            $table->foreign('supermarket_chain_id')->references('id')->on('supermarket_chains')->onDelete('cascade');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('restrict');
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('supermarket_chain_id')->unsigned();
            $table->integer('supermarket_branch_id')->unsigned()->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('discount_type', 60)->nullable();
            $table->decimal('discount_value', 12, 4)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->unsignedSmallInteger('day_of_week')->nullable();
            $table->boolean('requires_payment_method')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supermarket_chain_id', 'status']);
            $table->foreign('supermarket_chain_id')->references('id')->on('supermarket_chains')->onDelete('cascade');
            $table->foreign('supermarket_branch_id')->references('id')->on('supermarket_branches')->onDelete('cascade');
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->string('type', 60);
            $table->string('issuer', 120)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'type', 'issuer']);
        });

        Schema::create('promotion_payment_methods', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('promotion_id')->unsigned();
            $table->integer('payment_method_id')->unsigned();
            $table->timestamp('created_at')->nullable();

            $table->unique(['promotion_id', 'payment_method_id']);
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
        });

        Schema::create('user_payment_methods', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('payment_method_id')->unsigned();
            $table->string('alias', 120)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'payment_method_id', 'alias']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
        });

        Schema::create('supermarket_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->integer('supermarket_chain_id')->unsigned();
            $table->integer('supermarket_branch_id')->unsigned()->nullable();
            $table->string('external_product_id')->nullable();
            $table->string('external_sku')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_image_url')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->string('scrape_status', 30)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['supermarket_chain_id', 'supermarket_branch_id', 'status']);
            $table->index(['external_product_id', 'external_sku']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('supermarket_chain_id')->references('id')->on('supermarket_chains')->onDelete('cascade');
            $table->foreign('supermarket_branch_id')->references('id')->on('supermarket_branches')->onDelete('cascade');
        });

        Schema::create('supermarket_product_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supermarket_product_id');
            $table->decimal('price', 12, 2);
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->string('currency', 3)->default('ARS');
            $table->string('price_type', 60)->nullable();
            $table->integer('promotion_id')->unsigned()->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->string('source', 80)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('created_at')->nullable();

            $table->index(['supermarket_product_id', 'scraped_at']);
            $table->foreign('supermarket_product_id')->references('id')->on('supermarket_products')->onDelete('cascade');
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('set null');
        });

        Schema::create('branch_product_availability', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('supermarket_branch_id')->unsigned();
            $table->unsignedBigInteger('supermarket_product_id');
            $table->boolean('is_available')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('source', 80)->nullable();
            $table->string('status', 30)->default('active');

            $table->unique(['supermarket_branch_id', 'supermarket_product_id']);
            $table->foreign('supermarket_branch_id')->references('id')->on('supermarket_branches')->onDelete('cascade');
            $table->foreign('supermarket_product_id')->references('id')->on('supermarket_products')->onDelete('cascade');
        });

        Schema::create('geocode_cache', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('address')->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('provider', 80)->default('nominatim');
            $table->jsonb('raw_response')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        if (Schema::hasTable('family_groups') && Schema::hasColumn('family_groups', 'city_id')) {
            DB::statement('ALTER TABLE family_groups ALTER COLUMN city_id TYPE integer USING city_id::integer');
        }
    }

    public function down()
    {
        Schema::dropIfExists('geocode_cache');
        Schema::dropIfExists('branch_product_availability');
        Schema::dropIfExists('supermarket_product_prices');
        Schema::dropIfExists('supermarket_products');
        Schema::dropIfExists('user_payment_methods');
        Schema::dropIfExists('promotion_payment_methods');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('supermarket_branches');
        Schema::dropIfExists('supermarket_chains');
        Schema::dropIfExists('cities');
    }
}
