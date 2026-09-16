<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnsureRecipeTimestamps extends Migration
{
    public function up()
    {
        foreach (['created_at', 'updated_at'] as $column) {
            if (! Schema::hasColumn('recipes', $column)) {
                Schema::table('recipes', function (Blueprint $table) use ($column) {
                    $table->timestamp($column)->nullable();
                });
            }
        }
    }

    public function down()
    {
        // Legacy installations already owned these columns; preserve their data.
    }
}
