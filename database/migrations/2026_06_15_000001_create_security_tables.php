<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSecurityTables extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 40)->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'avatar_url')) {
                $table->string('avatar_url')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 30)->default('active')->after('avatar_url');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN lastname DROP NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN username DROP NOT NULL');
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 120)->unique();
            $table->string('module', 80);
            $table->string('action', 80);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('role_id')->unsigned();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'role_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('role_id')->unsigned();
            $table->integer('permission_id')->unsigned();
            $table->timestamp('created_at')->nullable();

            $table->unique(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('provider', 50);
            $table->string('provider_user_id');
            $table->string('provider_email')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('user_consents', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('consent_type', 80);
            $table->boolean('accepted')->default(false);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->index(['user_id', 'consent_type']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('action', 80);
            $table->string('entity_name', 120);
            $table->string('entity_id', 120)->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['entity_name', 'entity_id']);
            $table->index(['user_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('login_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('email')->nullable();
            $table->boolean('success')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('login_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_consents');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table) {
            foreach (['phone', 'avatar_url', 'status', 'last_login_at', 'deleted_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
