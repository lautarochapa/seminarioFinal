<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddCitiesAdminPermission extends Migration
{
    public function up()
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'web.admin.cities'],
            [
                'code'        => 'web.admin.cities',
                'module'      => 'web.admin',
                'action'      => 'access',
                'description' => 'Acceder a pantalla admin: cities',
                'status'      => 'active',
            ]
        );

        $permId = DB::table('permissions')
            ->where('code', 'web.admin.cities')
            ->value('id');

        if (! $permId) {
            return;
        }

        $superAdminId = DB::table('roles')->where('code', 'super_admin')->value('id');

        if ($superAdminId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $superAdminId, 'permission_id' => $permId],
                ['created_at' => $now]
            );
        }
    }

    public function down()
    {
        $permId = DB::table('permissions')
            ->where('code', 'web.admin.cities')
            ->value('id');

        if ($permId) {
            DB::table('role_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
}
