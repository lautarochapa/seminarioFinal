<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScrapingTables extends Migration
{
    public function up()
    {
        Schema::create('scraping_sources', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 100)->unique();
            $table->string('name', 150);
            $table->string('type', 60);
            $table->string('base_url')->nullable();
            $table->integer('city_id')->unsigned()->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
        });

        Schema::create('scraping_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('source_id')->unsigned();
            $table->string('job_type', 80);
            $table->integer('requested_by')->unsigned()->nullable();
            $table->string('status', 30)->default('pending');
            $table->jsonb('parameters_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('total_found')->default(0);
            $table->unsignedInteger('total_created')->default(0);
            $table->unsignedInteger('total_updated')->default(0);
            $table->unsignedInteger('total_pending_review')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'status']);
            $table->foreign('source_id')->references('id')->on('scraping_sources')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('scraping_job_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('scraping_job_id');
            $table->string('level', 30);
            $table->text('message');
            $table->jsonb('context_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['scraping_job_id', 'level']);
            $table->foreign('scraping_job_id')->references('id')->on('scraping_jobs')->onDelete('cascade');
        });

        Schema::create('scraped_product_candidates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('scraping_job_id');
            $table->integer('source_id')->unsigned();
            $table->string('raw_name');
            $table->string('raw_brand')->nullable();
            $table->decimal('raw_price', 12, 2)->nullable();
            $table->decimal('raw_unit_price', 12, 4)->nullable();
            $table->string('raw_image_url')->nullable();
            $table->string('raw_product_url')->nullable();
            $table->string('external_product_id')->nullable();
            $table->jsonb('raw_payload_json')->nullable();
            $table->unsignedBigInteger('suggested_product_id')->nullable();
            $table->integer('suggested_ingredient_id')->unsigned()->nullable();
            $table->decimal('match_confidence', 5, 2)->nullable();
            $table->string('review_status', 30)->default('pending');
            $table->integer('reviewed_by')->unsigned()->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'review_status']);
            $table->index(['external_product_id']);
            $table->foreign('scraping_job_id')->references('id')->on('scraping_jobs')->onDelete('cascade');
            $table->foreign('source_id')->references('id')->on('scraping_sources')->onDelete('cascade');
            $table->foreign('suggested_product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('suggested_ingredient_id')->references('id')->on('ingredients')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('product_match_candidates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('scraped_product_candidate_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('match_score', 5, 2);
            $table->text('match_reason')->nullable();
            $table->string('status', 30)->default('pending');
            $table->integer('reviewed_by')->unsigned()->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['scraped_product_candidate_id', 'product_id']);
            $table->foreign('scraped_product_candidate_id')->references('id')->on('scraped_product_candidates')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('scraping_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('scraping_job_id')->nullable();
            $table->integer('source_id')->unsigned();
            $table->string('alert_type', 80);
            $table->text('message');
            $table->string('severity', 30)->default('medium');
            $table->string('status', 30)->default('open');
            $table->integer('resolved_by')->unsigned()->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['source_id', 'status']);
            $table->foreign('scraping_job_id')->references('id')->on('scraping_jobs')->onDelete('cascade');
            $table->foreign('source_id')->references('id')->on('scraping_sources')->onDelete('cascade');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('scraping_errors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('scraping_job_id')->nullable();
            $table->integer('source_id')->unsigned();
            $table->string('error_type', 100);
            $table->text('message');
            $table->text('stack_trace')->nullable();
            $table->jsonb('context_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['source_id', 'error_type']);
            $table->foreign('scraping_job_id')->references('id')->on('scraping_jobs')->onDelete('cascade');
            $table->foreign('source_id')->references('id')->on('scraping_sources')->onDelete('cascade');
        });

        Schema::create('price_refresh_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unsigned();
            $table->unsignedBigInteger('product_id');
            $table->integer('supermarket_chain_id')->unsigned()->nullable();
            $table->integer('supermarket_branch_id')->unsigned()->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('supermarket_chain_id')->references('id')->on('supermarket_chains')->onDelete('set null');
            $table->foreign('supermarket_branch_id')->references('id')->on('supermarket_branches')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('price_refresh_requests');
        Schema::dropIfExists('scraping_errors');
        Schema::dropIfExists('scraping_alerts');
        Schema::dropIfExists('product_match_candidates');
        Schema::dropIfExists('scraped_product_candidates');
        Schema::dropIfExists('scraping_job_logs');
        Schema::dropIfExists('scraping_jobs');
        Schema::dropIfExists('scraping_sources');
    }
}
