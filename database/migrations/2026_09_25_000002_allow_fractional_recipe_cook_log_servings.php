<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AllowFractionalRecipeCookLogServings extends Migration
{
    public function up()
    {
        // Preserve existing integers and NULLs; historical rounded logs are not inferred or rewritten.
        DB::statement('ALTER TABLE recipe_cook_logs ALTER COLUMN servings TYPE NUMERIC(8,2)');
    }

    public function down()
    {
        DB::transaction(function () {
            // Keep the loss check and conversion atomic with respect to concurrent writers.
            DB::statement('LOCK TABLE recipe_cook_logs IN ACCESS EXCLUSIVE MODE');
            $wouldLoseValue = DB::table('recipe_cook_logs')->whereNotNull('servings')
                ->where(function ($query) {
                    $query->whereRaw('servings <> TRUNC(servings)')
                        ->orWhere('servings', '<', -32768)
                        ->orWhere('servings', '>', 32767);
                })->exists();
            if ($wouldLoseValue) {
                throw new RuntimeException('Cannot restore SMALLINT servings while fractional or out-of-range cook logs exist. Preserve those records and use a forward correction.');
            }
            DB::statement('ALTER TABLE recipe_cook_logs ALTER COLUMN servings TYPE SMALLINT');
        });
    }
}
