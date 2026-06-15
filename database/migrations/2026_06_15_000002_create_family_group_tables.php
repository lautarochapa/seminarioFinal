<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFamilyGroupTables extends Migration
{
    public function up()
    {
        Schema::create('family_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->integer('owner_user_id')->unsigned();
            $table->integer('city_id')->nullable();
            $table->string('default_address')->nullable();
            $table->decimal('default_latitude', 10, 7)->nullable();
            $table->decimal('default_longitude', 10, 7)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_user_id', 'status']);
            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('family_group_members', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('family_group_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->string('role_in_group', 50)->default('member');
            $table->string('status', 30)->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['family_group_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('family_group_invitations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('family_group_id')->unsigned();
            $table->string('invited_email');
            $table->integer('invited_user_id')->unsigned()->nullable();
            $table->integer('invited_by')->unsigned();
            $table->string('token')->unique();
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['family_group_id', 'status']);
            $table->index(['invited_email', 'status']);
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
            $table->foreign('invited_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('family_group_preferences', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('family_group_id')->unsigned();
            $table->string('default_budget_mode', 50)->nullable();
            $table->string('default_shopping_mode', 50)->nullable();
            $table->string('default_recipe_priority_mode', 50)->nullable();
            $table->boolean('allow_auto_stock_discount')->default(true);
            $table->timestamps();

            $table->unique('family_group_id');
            $table->foreign('family_group_id')->references('id')->on('family_groups')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('family_group_preferences');
        Schema::dropIfExists('family_group_invitations');
        Schema::dropIfExists('family_group_members');
        Schema::dropIfExists('family_groups');
    }
}
