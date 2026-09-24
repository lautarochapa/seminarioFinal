<?php

use Illuminate\Database\Migrations\Migration;

class SimplifyUserRoles extends Migration
{
    public function up()
    {
        \App\Services\Auth\RolePolicy::synchronize();
    }

    public function down()
    {
        // Restoring retired privileges requires an explicit reviewed migration.
    }
}
