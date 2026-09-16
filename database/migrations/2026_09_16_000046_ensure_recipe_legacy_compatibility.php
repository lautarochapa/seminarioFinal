<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnsureRecipeLegacyCompatibility extends Migration
{
    public function up()
    {
        // Current recipe services still write these legacy compatibility fields.
        foreach (['nombre', 'descripcion', 'tiempo', 'img', 'video', 'porcion', 'calorias'] as $column) {
            if (! Schema::hasColumn('recipes', $column)) {
                Schema::table('recipes', function (Blueprint $table) use ($column) {
                    if ($column === 'calorias') {
                        $table->integer($column)->nullable();
                    } else {
                        $table->string($column)->nullable();
                    }
                });
            }
        }
    }

    public function down()
    {
        // These fields may predate this repair on legacy installations.
    }
}
