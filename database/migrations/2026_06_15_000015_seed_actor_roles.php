<?php

use Illuminate\Database\Migrations\Migration;

class SeedActorRoles extends Migration
{
    public function up()
    {
        require_once database_path('seeds/SecuritySeeder.php');
        (new SecuritySeeder())->run();
    }

    public function down()
    {
        // Seed migration only. Roles and permissions are intentionally preserved on rollback.
    }
}
