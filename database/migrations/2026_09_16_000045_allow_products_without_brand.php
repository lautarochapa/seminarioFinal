<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AllowProductsWithoutBrand extends Migration
{
    public function up()
    {
        // The legacy schema required a brand even for unreviewed manual products.
        DB::statement('ALTER TABLE products ALTER COLUMN brand_id DROP NOT NULL');
    }

    public function down()
    {
        // Keep valid unbranded products; restoring NOT NULL would reject them.
    }
}
