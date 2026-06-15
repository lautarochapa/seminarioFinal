<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupplementTables extends Migration
{
    public function up()
    {
        Schema::create('supplement_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('user_supplements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unsigned();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('ingredient_id')->unsigned()->nullable();
            $table->unsignedBigInteger('supplement_type_id')->nullable();
            $table->decimal('dose_quantity', 12, 4)->nullable();
            $table->integer('dose_unit_id')->unsigned()->nullable();
            $table->string('frequency', 80)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('set null');
            $table->foreign('supplement_type_id')->references('id')->on('supplement_types')->onDelete('set null');
            $table->foreign('dose_unit_id')->references('id')->on('unit_measures')->onDelete('set null');
        });

        Schema::create('supplement_schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_supplement_id');
            $table->time('time_of_day')->nullable();
            $table->jsonb('days_of_week')->nullable();
            $table->boolean('reminder_enabled')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->foreign('user_supplement_id')->references('id')->on('user_supplements')->onDelete('cascade');
        });

        Schema::create('supplement_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_supplement_id');
            $table->integer('user_id')->unsigned();
            $table->timestamp('taken_at')->nullable();
            $table->decimal('dose_quantity', 12, 4)->nullable();
            $table->integer('dose_unit_id')->unsigned()->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'taken_at']);
            $table->foreign('user_supplement_id')->references('id')->on('user_supplements')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('dose_unit_id')->references('id')->on('unit_measures')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('supplement_logs');
        Schema::dropIfExists('supplement_schedules');
        Schema::dropIfExists('user_supplements');
        Schema::dropIfExists('supplement_types');
    }
}
