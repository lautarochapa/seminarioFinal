<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupportingSystemTables extends Migration
{
    public function up()
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('family_group_id')->unsigned()->nullable();
            $table->string('type', 80);
            $table->string('title', 180);
            $table->text('message')->nullable();
            $table->string('channel', 40)->default('app');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['family_group_id', 'type']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unsigned();
            $table->string('notification_type', 80);
            $table->boolean('app_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('push_enabled')->default(false);
            $table->string('frequency', 60)->default('immediate');
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'notification_type']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('family_group_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('report_type', 80);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->jsonb('data_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['family_group_id', 'report_type']);
            $table->index(['user_id', 'report_type']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('family_group_id')->unsigned()->nullable();
            $table->string('report_type', 80);
            $table->string('file_url', 500)->nullable();
            $table->string('format', 30);
            $table->string('status', 30)->default('pending');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('set null');
        });

        Schema::create('thesis_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft');
            $table->integer('created_by')->unsigned()->nullable();
            $table->integer('updated_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('thesis_document_sections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('title', 180);
            $table->text('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 30)->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_id', 'sort_order']);
            $table->foreign('document_id')->references('id')->on('thesis_documents')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('thesis_document_sections')->onDelete('cascade');
        });

        Schema::create('thesis_document_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('version_number');
            $table->jsonb('content_snapshot')->nullable();
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['document_id', 'version_number']);
            $table->foreign('document_id')->references('id')->on('thesis_documents')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('thesis_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('section_id')->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->text('comment');
            $table->string('status', 30)->default('open');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('thesis_documents')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('thesis_document_sections')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('demo_scenarios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('route', 255)->nullable();
            $table->integer('demo_user_id')->unsigned()->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('demo_user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->string('type', 40)->default('string');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 120)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('demo_scenarios');
        Schema::dropIfExists('thesis_comments');
        Schema::dropIfExists('thesis_document_versions');
        Schema::dropIfExists('thesis_document_sections');
        Schema::dropIfExists('thesis_documents');
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('report_snapshots');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_channels');
    }
}
